# Structure de Dossiers et de Fichiers - Projet Réseau Social

Ce document décrit en détail l'organisation des fichiers et des dossiers proposée pour le projet de réseau social en **PHP natif (API)** et **HTML/CSS/JavaScript (Frontend SPA via AJAX)**. Cette architecture est conforme aux exigences strictes de l'examen.

---

## 📁 Arborescence Complète du Projet

```text
CTPHP/
├── assets/                         # Ressources statiques pour le frontend
│   ├── css/                        # Feuilles de style CSS
│   │   ├── style.css               # Variables CSS, thèmes (clair/sombre), structure globale, animations
│   │   ├── auth.css                # Styles spécifiques aux écrans de connexion/inscription/forgot-password
│   │   ├── feed.css                # Styles du flux de publications, des likes et des commentaires
│   │   ├── chat.css                # Styles de la messagerie instantanée (barre latérale, bulles de messages)
│   │   └── admin.css               # Styles du panneau d'administration et des statistiques
│   ├── images/                     # Médias et icônes de l'application
│   │   ├── default-avatar.png      # Avatar par défaut pour les nouveaux utilisateurs
│   │   └── logo.png                # Logo de l'application
│   └── js/                         # Logique JavaScript (Client SPA & AJAX)
│       ├── app.js                  # Initialisation de l'application et coordination de l'état global
│       ├── router.js               # Gestionnaire de routes SPA (charge les gabarits HTML sans recharger la page)
│       ├── api.js                  # Module d'appels AJAX (Fetch API) unifié avec gestion de sessionStorage
│       ├── auth.js                 # Scripts d'authentification (login, register, password reset)
│       ├── feed.js                 # Gestion des publications, commentaires et likes/dislikes
│       ├── friends.js              # Logique des demandes d'amis et affichage des profils
│       ├── profile.js              # Modification des données personnelles et du mot de passe
│       ├── chat.js                 # Gestion du chat temps réel (polling AJAX toutes les 3 secondes)
│       └── admin.js                # Logique du Back-office (gestion des modérateurs, statistiques)
├── vues/                           # Vues HTML partielles chargées dynamiquement par router.js
│   ├── clients/                    # Vues de l'application utilisateur (Frontend)
│   │   ├── auth.html               # Formulaires d'authentification (Login, Inscription, Mot de passe oublié)
│   │   ├── feed.html               # Page d'accueil / Flux d'actualités
│   │   ├── friends.html            # Gestion des amis et suggestions de profils
│   │   ├── profile.html            # Affichage et modification du profil utilisateur
│   │   └── chat.html               # Interface du salon de chat
│   └── back-office/                # Vues d'administration (Back-Office)
│       ├── login.html              # Connexion dédiée aux administrateurs et modérateurs
│       └── dashboard.html          # Tableau de bord des statistiques et gestion de modération
├── api/                            # Backend PHP natif (fournit uniquement des réponses au format JSON)
│   ├── config/                     # Configuration de l'environnement backend
│   │   ├── db.php                  # Connexion PDO à la base de données MySQL
│   │   └── mail.php                # Configuration de l'envoi d'emails HTML (SMTP / mail natif)
│   ├── helpers/                    # Fonctions d'aide transversales
│   │   ├── auth_helper.php         # Validation des tokens de session et vérification des rôles (admin/mod)
│   │   └── response.php            # Formatage standard des réponses HTTP et JSON
│   ├── templates/                  # Gabarits HTML pour les courriels envoyés par l'API
│   │   ├── email_confirm.html      # Gabarit HTML pour l'email de confirmation d'inscription
│   │   └── email_reset.html        # Gabarit HTML pour l'email de réinitialisation de mot de passe
│   ├── auth/                       # Endpoints d'authentification
│   │   ├── register.php            # Inscription d'un utilisateur et envoi d'email
│   │   ├── login.php               # Validation des identifiants et retour de jeton de session
│   │   ├── forgot_password.php    # Demande de mot de passe oublié (génère un token de réinitialisation)
│   │   └── reset_password.php     # Application du nouveau mot de passe
│   ├── articles/                   # Endpoints liés aux articles
│   │   ├── list.php                # Récupération de tous les articles avec commentaires et likes associés
│   │   ├── create.php              # Création d'un article (gère l'envoi d'images optionnelles)
│   │   ├── like.php                # Enregistrement ou mise à jour d'un like/dislike
│   │   ├── comment.php             # Ajout d'un commentaire sur un article
│   │   └── delete.php              # Suppression d'un article (vérifie les droits d'auteur ou modérateur)
│   ├── friends/                    # Endpoints liés aux amis
│   │   ├── list.php                # Liste des relations d'amitié et demandes en attente
│   │   ├── request.php             # Envoi d'une demande d'ami
│   │   └── respond.php             # Acceptation ou rejet d'une invitation d'ami
│   ├── profile/                    # Endpoints liés aux profils utilisateurs
│   │   ├── get.php                 # Obtention des données d'un profil (publique ou privé)
│   │   ├── update.php              # Mise à jour des informations et photo de profil (upload)
│   │   └── change_password.php     # Remplacement sécurisé du mot de passe
│   ├── chat/                       # Endpoints liés à la messagerie instantanée
│   │   ├── conversations.php       # Liste des personnes avec qui des messages ont été échangés
│   │   └── messages.php            # Envoi et réception de messages (texte et images)
│   └── admin/                      # Endpoints réservés au Back-office (Modérateur / Admin)
│       ├── stats.php               # Statistiques détaillées de la plateforme
│       ├── users.php               # Suppression ou blocage d'utilisateurs
│       └── moderators.php          # Ajout/suppression de modérateurs (restreint aux Administrateurs)
├── database.sql                    # Script de structure SQL pour MySQL (tables, contraintes de clés étrangères)
├── index.html                      # Page HTML racine. Point d'entrée unique de la SPA (Single Page Application)
└── README.md                       # Description du projet, membres du groupe, et instructions de déploiement
```

---

## 📄 Rôle Détaillé de Chaque Composant

### 1. Fichiers à la Racine

*   **index.html** : C'est le fichier d'entrée principal et unique de l'application. Il contient la structure HTML globale commune (en-tête de navigation, conteneur de message d'erreur/succès, conteneur principal `#app-container` où seront injectées les vues partielles, et importations des scripts JS et fichiers CSS). Aucun rechargement de page ne s'effectue à partir de ce fichier.
*   **database.sql** : Fichier SQL contenant les instructions `CREATE TABLE` pour configurer la base de données MySQL. Il contient la structure pour les utilisateurs (avec rôles : `client`, `moderator`, `administrator`), les articles, les commentaires, les likes/dislikes, les invitations d'amis, les messages de chat, ainsi que des données initiales d'exemple (seeders) pour tester le système de connexion.
*   **README.md** : Document obligatoire requis pour la remise du projet. Il liste les membres du groupe, décrit le fonctionnement de l'application, explique comment importer la base de données et fournit les identifiants de test (compte client standard et compte administrateur/modérateur).

---

### 2. Dossier des Ressources Client (`assets/`)

Ce dossier regroupe tous les fichiers de présentation et de logique exécutés sur le navigateur de l'utilisateur.

#### A. Feuilles de Style (`assets/css/`)
*   **style.css** : Définit la charte graphique globale de l'application (variables CSS pour les couleurs inspirées du thème sombre/clair de Facebook, polices, réinitialisation de style CSS standard, mise en page globale en Grids et Flexbox).
*   **auth.css** : Stylise les boîtes de dialogue et formulaires de connexion, de création de compte et de récupération de mot de passe.
*   **feed.css** : Gère le style des articles (cartes, avatar de l'auteur, image de publication, boutons d'interaction like/dislike, liste déroulante des commentaires et champ de saisie de commentaire fixe).
*   **chat.css** : Style la barre latérale des discussions actives, l'alignement des bulles de messages (gauche pour le correspondant, droite pour l'utilisateur), et l'affichage des images partagées.
*   **admin.css** : Style l'interface de gestion (Back-office) comprenant les widgets de statistiques, les tableaux d'utilisateurs et de publications, et les boutons d'action rapide (supprimer, promouvoir).

#### B. Scripts JavaScript (`assets/js/`)
*   **app.js** : Script central qui s'exécute dès le chargement initial d'index.html. Il vérifie l'existence d'une session dans `sessionStorage` pour orienter le routeur, initialise les composants globaux et gère les événements génériques (déconnexion, affichage des notifications).
*   **router.js** : Implémente le mécanisme de Single Page Application (SPA). Il intercepte les clics sur les liens de navigation, met à jour l'URL virtuelle (via l'API History) et effectue une requête AJAX (Fetch) pour charger le code HTML partiel correspondant depuis le dossier `vues/` afin de l'injecter dans le conteneur principal `#app-container`.
*   **api.js** : Module responsable de toutes les requêtes Fetch HTTP vers l'API PHP (`api/`). Il intercepte les requêtes pour y ajouter automatiquement le jeton d'authentification ou les cookies de session, et gère de manière centralisée les erreurs HTTP (comme une session expirée redirigeant vers la connexion).
*   **auth.js** : Gère la soumission asynchrone des formulaires de connexion et d'inscription. En cas de succès de connexion, il enregistre les données de session utilisateur dans le `sessionStorage` et redirige vers le flux d'actualités.
*   **feed.js** : Charge les articles depuis l'API backend et les restitue sous forme de cartes HTML interactives. Gère la publication de nouveaux articles (avec validation d'image côté client), les actions de clic sur like/dislike, et le traitement asynchrone de l'envoi de commentaires.
*   **friends.js** : Met à jour dynamiquement la liste d'amis, traite les boutons d'action (envoyer, accepter, refuser une demande d'ami) et affiche le profil des autres utilisateurs.
*   **profile.js** : Gère le formulaire de mise à jour des informations utilisateur (nom, prénom) et convertit ou prépare l'envoi multipart (FormData) pour l'upload de l'avatar.
*   **chat.js** : Gère l'interface de messagerie. Il contient la logique de **polling par intervalle** (exécute un `setInterval` toutes les 3 secondes pour interroger `api/chat/messages.php` afin de récupérer les nouveaux messages sans rechargement de page). Il gère également l'envoi de messages textuels et d'images.
*   **admin.js** : Contrôle l'accès à l'interface d'administration, met à jour le tableau de bord avec les données renvoyées par l'API de statistiques et exécute les requêtes de bannissement/suppression d'utilisateurs ou d'articles.

---

### 3. Dossier des Vues HTML (`vues/`)

Ce dossier héberge les morceaux de codes HTML (templates ou layouts) qui ne contiennent pas de logique serveur PHP, mais seulement la structure HTML nécessaire pour chaque vue. Ils sont récupérés dynamiquement par le client.

#### A. Client Lambda (`vues/clients/`)
*   **auth.html** : Contient le formulaire d'inscription, le formulaire de connexion et le formulaire de récupération de mot de passe (forgot password), qui s'affichent ou se masquent selon l'action demandée.
*   **feed.html** : Squelette HTML du flux d'actualités. Comporte un formulaire de publication d'articles au sommet (avec champ texte et bouton de téléversement d'image) et une zone cible `#feed-posts` où seront injectés les articles.
*   **friends.html** : Structure HTML pour afficher trois listes distinctes : "Mes Amis", "Demandes d'amis reçues" et "Trouver des amis" (liste générale des inscrits).
*   **profile.html** : Layout d'affichage et d'édition des informations du compte. Inclut un formulaire pré-rempli et des champs dédiés au changement de mot de passe.
*   **chat.html** : Structure de l'application de discussion, composée d'une colonne latérale affichant la liste des contacts et conversations actives, et d'un conteneur central pour l'échange de messages avec un champ d'écriture et un bouton d'envoi d'image.

#### B. Back-Office (`vues/back-office/`)
*   **login.html** : Interface de connexion épurée réservée aux modérateurs et aux administrateurs.
*   **dashboard.html** : Tableau de bord d'administration présentant les rapports chiffrés (statistiques) sous forme de cartes, ainsi que des tables listant les utilisateurs et les articles signalés pour action de modération rapide.

---

### 4. Dossier de l'API Backend PHP (`api/`)

Ce dossier représente la totalité du backend. Il agit en tant qu'API RESTful. Il traite exclusivement les requêtes asynchrones en provenance du JavaScript client et retourne des réponses standardisées au format JSON.

#### A. Configurations & Utilitaires (`api/config/`, `api/helpers/`)
*   **db.php** : Configure et retourne une instance globale de PDO connectée à la base de données MySQL. Il gère la configuration du jeu de caractères UTF-8 et active les exceptions en cas d'erreur de requête SQL.
*   **mail.php** : Fournit des fonctions d'envoi d'emails. Il configure les en-têtes requis pour l'envoi de messages HTML (type MIME, encodage) et implémente l'envoi via la fonction `mail()` de PHP.
*   **auth_helper.php** : Valide le jeton d'authentification ou la session envoyé dans les en-têtes HTTP de chaque requête API. Il extrait l'identifiant de l'utilisateur connecté et contrôle si son rôle est suffisant pour accéder aux pages d'administration.
*   **response.php** : Contient des fonctions utilitaires (comme `sendJSON($data, $status)`) pour définir automatiquement l'en-tête de réponse `Content-Type: application/json` et renvoyer le code HTTP adapté (ex: 200 OK, 400 Bad Request, 401 Unauthorized, 403 Forbidden).

#### B. Modèles d'Emails (`api/templates/`)
*   **welcome_email.html** : Code HTML stylisé d'un email de bienvenue envoyé à l'utilisateur lors de son inscription, contenant un lien de confirmation.
*   **reset_email.html** : Code HTML stylisé contenant les instructions et un lien sécurisé à usage unique pour réinitialiser le mot de passe d'un utilisateur.

#### C. Endpoints API (Organisés par Module)

##### Authentification (`api/auth/`)
*   **register.php** : Reçoit en POST le nom, prénom, email et mot de passe. Il vérifie l'unicité de l'email, hache le mot de passe (via `password_hash`), insère l'utilisateur en base avec un statut "en attente" et lui envoie le courriel de confirmation à l'aide de `welcome_email.html`.
*   **login.php** : Reçoit les identifiants en POST. Si les identifiants sont valides, il génère un jeton de session (ou démarre une session PHP) et retourne les informations de l'utilisateur ainsi que le jeton au client pour enregistrement dans son `sessionStorage`.
*   **forgot_password.php** : Génère un token de réinitialisation expirable associé à l'adresse email reçue en POST, et envoie le courriel de réinitialisation en utilisant `reset_email.html`.
*   **reset_password.php** : Valide le jeton de réinitialisation reçu et met à jour le mot de passe dans la base de données.

##### Publications et Flux d'Actualités (`api/articles/`)
*   **list.php** : Récupère et retourne la liste des publications avec les informations sur l'auteur (avatar, nom, prénom), le nombre total de likes et dislikes, le statut de réaction de l'utilisateur actuel, et les commentaires associés.
*   **create.php** : Crée une publication en insérant le texte en base. Si une image est fournie, il vérifie sa validité, la renomme et la télécharge dans le dossier des images utilisateurs, puis enregistre son chemin d'accès.
*   **like.php** : Permet à un utilisateur de liker ou disliker un article (insère la réaction ou la supprime si l'utilisateur clique à nouveau).
*   **comment.php** : Ajoute un commentaire lié à un article spécifique en l'associant à l'utilisateur connecté.
*   **delete.php** : Supprime un article et ses fichiers associés de la base de données (accessible uniquement par l'auteur de l'article ou par un modérateur/administrateur).

##### Relations d'Amis (`api/friends/`)
*   **list.php** : Renvoie la liste de tous les utilisateurs enregistrés avec l'état de leur relation avec le demandeur (Non ami, Demande envoyée, Demande reçue, Ami).
*   **invite.php** : Envoie une demande d'ami à un autre utilisateur (crée un enregistrement avec un statut `en_attente`).
*   **respond.php** : Reçoit la réponse à une demande d'ami. Permet de passer le statut à `confirme` (demande acceptée) ou de supprimer l'enregistrement (demande refusée/annulée).

##### Gestion de Profil (`api/profile/`)
*   **get.php** : Récupère les données publiques d'un utilisateur ou les données complètes de l'utilisateur actuellement authentifié.
*   **update.php** : Met à jour les informations du profil (nom, prénom) et gère le téléversement d'une nouvelle photo de profil.
*   **change_password.php** : Vérifie l'ancien mot de passe fourni par l'utilisateur connecté et applique le nouveau après l'avoir haché.

##### Messagerie Instantanée (Chat) (`api/chat/`)
*   **conversations.php** : Récupère la liste des utilisateurs avec lesquels le demandeur a des conversations en cours (triée par la date du dernier message).
*   **messages.php** :
    *   **GET** : Récupère l'historique des messages entre l'utilisateur connecté et un destinataire spécifique (avec une clause temporelle pour ne récupérer que les messages plus récents lors du polling de 3 secondes).
    *   **POST** : Envoie un message texte ou téléverse une image vers le destinataire.

##### Administration (Back-office) (`api/admin/`)
*   **stats.php** : Calcule et renvoie les indicateurs clés pour le dashboard : nombre d'inscrits, nombre d'articles publiés, nombre de messages de chat envoyés, et statistiques d'activité récentes.
*   **users.php** : Permet aux modérateurs et aux administrateurs de l'application d'examiner et de supprimer des comptes utilisateurs.
*   **moderators.php** : Endpoint exclusif à l'Administrateur pour promouvoir un utilisateur au rôle de Modérateur/Admin ou le rétrograder.

---

## 🛠️ Réponses aux Contraintes Techniques

1.  **Zéro rechargement (SPA)** : Géré par `router.js` et `index.html`. Toutes les transitions d'écrans se feront par injection de gabarits HTML partiels de `vues/` et par requêtes asynchrones Fetch.
2.  **sessionStorage** : Le jeton de session retourné par `login.php` est conservé côté client via l'API `sessionStorage` du navigateur. Ce jeton est automatiquement joint à chaque appel API via `api.js` (dans les en-têtes Authorization) pour authentifier l'utilisateur.
3.  **Chat en temps réel sans sockets (Polling)** : Le script JavaScript `chat.js` effectue une requête AJAX répétée toutes les 3 secondes vers `messages.php` en précisant la date du dernier message connu pour ne récupérer que les nouveaux éléments et limiter la consommation de bande passante.
4.  **Envoi de courriels HTML** : Le script utilitaire `mail.php` lit et injecte les données dynamiques dans les fichiers HTML de `api/templates/` pour expédier des emails riches, clairs et structurés.