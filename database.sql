-- Base de données pour le Réseau Social (TP PHP/AJAX)
-- Conforme aux noms français utilisés par les API PHP
CREATE DATABASE IF NOT EXISTS `social_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `social_db`;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS `utilisateurs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(50) NOT NULL,
    `prenom` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `avatar` VARCHAR(255) DEFAULT 'default-avatar.png',
    `role` ENUM('client', 'moderator', 'administrator') DEFAULT 'client',
    `statut` VARCHAR(20) DEFAULT 'actif',
    `token_activation` VARCHAR(255) DEFAULT NULL,
    `date_inscription` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des articles (publications)
CREATE TABLE IF NOT EXISTS `articles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_utilisateur` INT NOT NULL,
    `description` TEXT NOT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `date_publication` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des likes/dislikes
CREATE TABLE IF NOT EXISTS `likes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_article` INT NOT NULL,
    `id_utilisateur` INT NOT NULL,
    `type` ENUM('like', 'dislike') DEFAULT 'like',
    `date_like` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `user_article_reaction` (`id_utilisateur`, `id_article`),
    FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`id_article`) REFERENCES `articles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des commentaires
CREATE TABLE IF NOT EXISTS `commentaires` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_article` INT NOT NULL,
    `id_utilisateur` INT NOT NULL,
    `contenu` TEXT NOT NULL,
    `date_commentaire` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`id_article`) REFERENCES `articles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des relations d'amitié
CREATE TABLE IF NOT EXISTS `amis` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_demandeur` INT NOT NULL,
    `id_receveur` INT NOT NULL,
    `statut` ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
    `date_affiliation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `friend_pair` (`id_demandeur`, `id_receveur`),
    FOREIGN KEY (`id_demandeur`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`id_receveur`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de messagerie (chat)
CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_expediteur` INT NOT NULL,
    `id_destinataire` INT NOT NULL,
    `contenu` TEXT DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `date_message` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_expediteur`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`id_destinataire`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--identtifiants pour les tests (le mot de passe est "password123" pour tous les utilisateurs)
INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `password`, `avatar`, `role`, `statut`, `token_activation`, `date_inscription`) VALUES
(26, 'Luc', 'Jean', 'jean@gmail.com', '$2y$10$BReCB8j9tRRkiK8SpKc/3.UyPKpZLxNdBrGikF2Hq.K4G1vPOyBOm', 'default-avatar.png', 'client', 'actif', NULL, '2026-07-05 14:52:11'),
(27, 'Jeanne', 'Marie', 'marie@gmail.com', '$2y$10$huA5kC4Y7i0XkRqiXVu0ve1QidQivt86UDgO20MuHiCyNt24UPyCy', 'default-avatar.png', 'client', 'actif', NULL, '2026-07-05 14:53:27'),
(28, 'Global', 'Admin', 'adming@gmail.com', '$2y$10$cCEo76EM2xF8x3mPpMYmc.KYpi5jzaHl.DPFZSQ2YIxCYgKs4.HDa', 'default-avatar.png', 'administrator', 'actif', NULL, '2026-07-05 14:54:50'),
(29, 'Social', 'modo1', 'm1@gmail.com', '$2y$10$r3.Xm/o2.4/v91lvb7AGc.9xTJ6lUMgDxa2aF2FhutmjNYNNrRpqC', 'default-avatar.png', 'moderator', 'actif', NULL, '2026-07-05 14:55:43'),
(30, 'Social', 'modo2', 'm2@gmail.com', '$2y$10$o4WJY2tmWqQkIZsPj93PkO.m4ynqT1dMg82pgu9Ty39nTenYsxLFa', 'default-avatar.png', 'moderator', 'actif', NULL, '2026-07-05 14:56:28');
COMMIT;