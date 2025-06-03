<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';

session_start();

// Vérification de l'authentification et du rôle
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'eleve') {
    header('Location: ../login.php');
    exit();
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Présences - Élève</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Espace Élève</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Tableau de bord</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="emploi_du_temps.php">Emploi du temps</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="presences.php">
                            <i class="bi bi-person-check"></i> Mes présences
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../logout.php">Déconnexion</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="bi bi-person-check"></i> Mes Présences</h2>

        <div class="alert alert-info">
            <h4><i class="bi bi-info-circle"></i> Fonctionnalité en développement</h4>
            <p class="mb-0">Le système de gestion des présences est en cours de développement.</p>
            <p class="mb-0">Vous pourrez bientôt :</p>
            <ul class="mt-2 mb-0">
                <li>Signer votre présence aux cours</li>
                <li>Consulter votre historique de présences</li>
                <li>Voir vos statistiques de présence</li>
                <li>Justifier vos absences</li>
            </ul>
        </div>

        <!-- Statistiques simulées -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-center bg-success text-white">
                    <div class="card-body">
                        <i class="bi bi-check-circle fs-1"></i>
                        <h5 class="mt-2">Présences</h5>
                        <h3>--</h3>
                        <small>cours présents</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-danger text-white">
                    <div class="card-body">
                        <i class="bi bi-x-circle fs-1"></i>
                        <h5 class="mt-2">Absences</h5>
                        <h3>--</h3>
                        <small>cours absents</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-warning text-white">
                    <div class="card-body">
                        <i class="bi bi-clock fs-1"></i>
                        <h5 class="mt-2">Retards</h5>
                        <h3>--</h3>
                        <small>retards</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-info text-white">
                    <div class="card-body">
                        <i class="bi bi-percent fs-1"></i>
                        <h5 class="mt-2">Taux</h5>
                        <h3>--%</h3>
                        <small>de présence</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Retour au tableau de bord -->
        <div class="card mt-4">
            <div class="card-body text-center">
                <h5>En attendant...</h5>
                <p>Vous pouvez consulter votre emploi du temps et votre tableau de bord.</p>
                <a href="dashboard.php" class="btn btn-primary me-2">
                    <i class="bi bi-house"></i> Tableau de bord
                </a>
                <a href="emploi_du_temps.php" class="btn btn-outline-primary">
                    <i class="bi bi-calendar-week"></i> Emploi du temps
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>