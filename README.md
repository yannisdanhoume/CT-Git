# 📱 SocialConnect - Réseau Social Web en PHP et AJAX

**SocialConnect** est une application web complète de type réseau social inspirée de **Facebook**, développée comme une **Single Page Application (SPA)** sans aucun rechargement de page après le chargement initial. L'application offre toutes les fonctionnalités essentielles d'un réseau social moderne : authentification sécurisée, gestion des amis, messagerie instantanée, flux de publications, profils utilisateur et un back-office d'administration complet.

---

## 👥 Membres du Groupe

**Numéro du groupe** : 9
- DANHOUME Yannis
- BAGUIDI Boris
- AHOUNOU Bright
- ATINDOKPO Pharell

---

## 🎯 Description du Projet

Ce projet est une implémentation complète des exigences du **TP Réseau Social Web en PHP et AJAX**. L'application inclut :

- **Module d'Authentification** : Inscription avec confirmation email, connexion sécurisée, gestion du mot de passe oublié
- **Flux d'Articles** : Affichage des publications avec images, système de likes/dislikes, commentaires en direct
- **Gestion des Amis** : Consultation des utilisateurs, envoi/réception/gestion des demandes d'amitié
- **Messagerie Instantanée** : Chat en temps réel avec recherche de contacts, envoi de messages textuels et d'images
- **Profils Utilisateurs** : Visualisation et modification des informations personnelles, gestion du mot de passe
- **Back-Office d'Administration** : Dashboard avec statistiques, gestion des articles/utilisateurs, rôles (Admin/Modérateur)

---
## 📋 Mode de Fonctionnement

### 1️⃣ Authentification
- **Inscription** : Validation des données, envoi d'email de confirmation avec lien de vérification
- **Connexion** : Authentification sécurisée via Bearer token stocké dans sessionStorage
- **Mot de passe oublié** : Envoi d'email avec lien de réinitialisation
- **Déconnexion** : Suppression du token et redirection vers l'accueil

### 2️⃣ Fil d'Actualités (Feed)
- Affichage des publications de tous les utilisateurs (avatar, nom/prénom, texte, image optionnelle)
- **Likes/Dislikes** : Système avec persistance ; icône change de couleur selon le statut
- **Commentaires** : Affichage dynamique et ajout sans rechargement de page
- **Auto-rafraîchissement** : Polling automatique toutes les 4 secondes pour les nouvelles publications

### 3️⃣ Gestion des Amis
- **Liste des utilisateurs** : Consultation avec bouton pour envoyer une demande d'amitié
- **Gestion des demandes** : Accepter/refuser les demandes reçues
- **Auto-chat** : Une conversation est automatiquement créée avec le message "salut" lors de l'acceptation
- **Profils publics** : Consultation des profils d'autres utilisateurs avec nombre d'amis, statut, publications

### 4️⃣ Messagerie Instantanée (Chat)
- **Sidebar** : Affichage des conversations existantes avec noms de partenaires et dernier message
- **Recherche d'amis** : Lancer une conversation avec n'importe quel ami (nouvelle ou existante)
- **Affichage des messages** : Conversations sans rechargement, avec timestamps
- **Envoi de médias** : Support des images attachées aux messages
- **Auto-rafraîchissement** : Polling toutes les 2.5 secondes pour la réactivité maximale

### 5️⃣ Profil Utilisateur
- **Modification des informations** : Édition du prénom, nom, avatar, biographie
- **Changement de mot de passe** : Formulaire sécurisé avec validation
- **Consulter les publications** : Affichage des articles personnels de l'utilisateur

### 6️⃣ Back-Office d'Administration
#### 📊 Dashboard Administrateur
- **Statistiques** : Nombre total d'utilisateurs, articles, messages
- **Liste des comptes** : Tous les utilisateurs avec leurs statuts (actif/bloqué) et rôles
- **Gestion complète** : Suppression d'articles/utilisateurs, modification des statuts et des rôles (Admin/Modérateur)

#### 👮 Modérateur
- **Suppression d'articles** : Contrôle des contenus inappropriés
- **Gestion des utilisateurs** : Suppression ou blocage de comptes clients (pas d'action sur Admin/Mod)
- Accès en lecture aux statistiques

---

## 🔐 Identifiants de Test

Les mots de passe de tous les comptes par défaut sont **`password123`**.

### Utilisateurs Admin et Modérateur
| Rôle | Email | Mot de passe | Accès |
| :--- | :--- | :--- | :--- |
| **Administrateur** | `adming@gmail.com` | `password123` | Back-office complet, gestion des rôles |
| **Modérateur** | `m1@gmail.com` | `password123` | Modération articles et utilisateurs |
| **Modérateur** | `m2@gmail.com` | `password123` | Modération articles et utilisateurs |

### Utilisateurs Clients (Test)
| Email | Mot de passe | Statut |
| :--- | :--- | :--- |
| `jean@gmail.com` | `password123` | Client standard |
| `marie@gmail.com` | `password123` | Client standard |

---

## ⚙️ Installation et Déploiement

### ✅ Prérequis
- Serveur local : **XAMPP**, **WampServer** ou équivalent
- **PHP 7.4+** avec support PDO
- **MySQL 5.7+** ou **MariaDB**
- Navigateur moderne avec JavaScript activé
- FakeSMTP pour intercepter les emails de confirmation (en écoute sur le port 25)

#### Configuration
- Les paramètres par défaut : `localhost`, utilisateur `root`, mot de passe vide
---

## 📁 Architecture des Fichiers

L'organisation complète du projet est documentée dans [structure_projet.md]

### 📂 Structure Résumée
```
CTPHP/
├── assets/               # Ressources statiques (CSS, JS, images)
├── vues/                 # Vues HTML partielles (clients + back-office)
├── api/                  # Backend PHP (endpoints JSON)
│   ├── auth/            # Authentification
│   ├── articles/        # Gestion des publications
│   ├── chat/            # Messagerie
│   ├── friends/         # Gestion des amis
│   ├── profile/         # Profils utilisateurs
│   ├── admin/           # Administration
│   └── config/          # Configuration DB et mail
├── index.html           # Entrée principale SPA
└── database.sql         # Schéma de base de données
```

---

## 🚀 Fonctionnalités Implémentées

### ✅ Module Authentification
- [x] Inscription avec confirmation par email (HTML template)
- [x] Connexion sécurisée via Bearer token
- [x] Gestion du mot de passe oublié
- [x] Réinitialisation par lien email

### ✅ Flux d'Articles
- [x] Création de publications avec texte et image
- [x] Affichage avec avatar, nom, prénom
- [x] Système likes/dislikes avec persistance utilisateur
- [x] Commentaires avec ajout en direct (sans rechargement)
- [x] Auto-rafraîchissement (polling 4s)

### ✅ Gestion des Amis
- [x] Liste des utilisateurs avec suggestions
- [x] Envoi de demandes d'amitié
- [x] Accepter/refuser les demandes
- [x] Auto-création de conversation ("salut") à l'acceptation
- [x] Consultation des profils publics

### ✅ Messagerie
- [x] Sidebar avec conversations existantes
- [x] Recherche d'amis pour démarrer une conversation
- [x] Affichage des messages sans rechargement
- [x] Envoi de messages textuels et d'images
- [x] Auto-rafraîchissement (polling 2.5s)

### ✅ Profil Utilisateur
- [x] Modification des informations personnelles
- [x] Changement de mot de passe
- [x] Consulter les publications personnelles

### ✅ Back-Office Admin
- [x] Page de connexion dédiée
- [x] Dashboard avec statistiques (utilisateurs, articles, messages)
- [x] Liste des comptes avec statuts et rôles
- [x] Gestion des articles et utilisateurs
- [x] Rôles : Administrateur et Modérateur

### ✅ Améliorations Techniques
- [x] Single Page Application (SPA) sans rechargement
- [x] Pas de recharge après le chargement initial
- [x] Sessions via JavaScript sessionStorage
- [x] Emails au format HTML
- [x] Code sécurisé et bien structuré
- [x] Transactions DB pour opérations atomiques
- [x] Polling optimisé (count-based) pour les performances

---

## 🔒 Sécurité

- **Authentification** : Bearer token avec sessionStorage
- **SQL Injection** : Requêtes préparées via PDO
- **CSRF** : Tokens validés côté serveur
- **Mots de passe** : Hachage bcrypt
- **Autorisation** : Vérification des rôles (admin/mod) à chaque requête

---

## 📝 Notes de Développement

- Polling utilisé pour la simulation temps réel (conforme aux exigences)
- Pas de framework backend, PHP natif uniquement
- Architecture modulaire et maintenable
- Tous les fichiers PHP validés syntaxiquement
- Tous les fichiers JavaScript validés syntaxiquement