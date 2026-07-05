<?php
// Utilitaire d'envoi de courriels au format HTML
// Configuration SMTP pour FakeSMTP (localhost:25 ou 1025)

/**
 * Envoie un e-mail via SMTP (FakeSMTP compatible)
 */
function sendHTMLMail($to, $subject, $body) {
    $from = "no-reply@socialconnect.local";
    $smtpHost = "localhost";
    $smtpPort = 25; // FakeSMTP par défaut utilise 25, changez à 1025 si nécessaire
    
    // Construction du message email
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: SocialConnect <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    
    $message = "To: {$to}\r\n";
    $message .= "Subject: {$subject}\r\n";
    $message .= $headers . "\r\n";
    $message .= $body;
    
    // Tentative de connexion SMTP
    $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 10);
    if (!$socket) {
        // Fallback vers mail() native si SMTP indisponible
        return @mail($to, $subject, $body, $headers);
    }
    
    $response = @fgets($socket);
    if (substr($response, 0, 3) != '220') {
        fclose($socket);
        return @mail($to, $subject, $body, $headers);
    }
    
    // Dialogue SMTP
    fputs($socket, "HELO localhost\r\n");
    fgets($socket);
    
    fputs($socket, "MAIL FROM: <{$from}>\r\n");
    fgets($socket);
    
    fputs($socket, "RCPT TO: <{$to}>\r\n");
    fgets($socket);
    
    fputs($socket, "DATA\r\n");
    fgets($socket);
    
    fputs($socket, $message . "\r\n.\r\n");
    fgets($socket);
    
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    return true;
}

/**
 * Charge un gabarit (template) HTML, remplace les variables dynamiques et envoie l'e-mail.
 * * @param string $to Adresse e-mail du destinataire
 * @param string $subject Objet de l'e-mail
 * @param string $templateName Nom du fichier HTML dans le dossier templates (ex: 'email_confirm.html')
 * @param array $data Tableau associatif des variables à remplacer (ex: ['{NOM}' => 'John', '{LIEN}' => '...'])
 * @return bool True en cas de succès, False en cas d'échec
 */
function sendTemplateMail($to, $subject, $templateName, $data = []) {
    // Chemin absolu vers le fichier de template HTML
    $templatePath = __DIR__ . '/../templates/' . $templateName;
    
    // Vérification de l'existence du fichier template
    if (!file_exists($templatePath)) {
        // Log de l'erreur en interne pour le développeur
        error_log("Erreur Mail : Le template HTML introuvable : " . $templatePath);
        return false;
    }
    
    // Lecture du contenu du fichier HTML
    $body = file_get_contents($templatePath);
    
    // Remplacement des placeholders par les vraies valeurs dynamiques
    if (!empty($data)) {
        $search = array_keys($data);
        $replace = array_values($data);
        $body = str_replace($search, $replace, $body);
    }
    
    // Envoi final via la fonction de base
    return sendHTMLMail($to, $subject, $body);
}