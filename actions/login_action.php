<?php
session_start();
require '../config/config.php';
require '../classes/Auth.php';
require '../classes/Database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $auth->login($email, $password);

    if ($role) {
        switch ($role) {
            case 'admin':
                header("Location: ../public/dashboard_admin.php");
                break;
            case 'enseignant':
                header("Location: ../public/dashboard_enseignant.php");
                break;
            case 'eleve':
                header("Location: ../public/dashboard_eleve.php");
                break;
        }
        exit();
    } else {
        header("Location: ../public/login.php?error=1");
        exit();
    }
}
