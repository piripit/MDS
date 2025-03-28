<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestion_classes');
define('DB_USER', 'root');
define('DB_PASS', 'root');

// Configuration de l'application
define('APP_URL', 'http://localhost/MDS');
define('APP_NAME', 'Gestion des Classes');

// Configuration des emails
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'votre_email@gmail.com');
define('SMTP_PASS', 'votre_mot_de_passe');
define('SMTP_FROM', 'votre_email@gmail.com');
define('SMTP_FROM_NAME', 'Gestion des Classes');

// Configuration des sessions
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Configuration du fuseau horaire
date_default_timezone_set('Europe/Paris');

// Configuration des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration de la connexion PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Fonctions utilitaires
function redirect($url)
{
    header("Location: " . $url);
    exit();
}

function sanitize($input)
{
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

function generateToken()
{
    return bin2hex(random_bytes(32));
}

// Gestion des erreurs
function handleError($errno, $errstr, $errfile, $errline)
{
    error_log("Erreur [$errno] $errstr dans $errfile à la ligne $errline");
    return true;
}

set_error_handler('handleError');
