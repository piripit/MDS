<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';

session_start();

// Vérifier si l'utilisateur est connecté et est un élève
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'eleve') {
    header('Location: ../login.php');
    exit;
}
