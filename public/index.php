<?php
require_once '../config/config.php';
require_once '../classes/Auth.php';

session_start();

// Vérifier si l'utilisateur est connecté
if (Auth::isLoggedIn()) {
    $user = $_SESSION['user'];

    // Redirection selon le rôle
    switch ($user['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            exit;
        case 'enseignant':
            header('Location: enseignant/dashboard.php');
            exit;
        case 'eleve':
            header('Location: eleve/dashboard.php');
            exit;
        default:
            header('Location: login.php');
            exit;
    }
} else {
    header('Location: login.php');
    exit;
}
