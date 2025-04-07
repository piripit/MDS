<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';

session_start();

// Vérifier si l'utilisateur est connecté et est un enseignant
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'enseignant') {
    header('Location: ../login.php');
    exit;
}
