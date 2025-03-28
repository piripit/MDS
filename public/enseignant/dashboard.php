<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';
require_once '../../classes/EmploiDuTemps.php';
require_once '../../classes/Signature.php';

session_start();

// Vérification de l'authentification et du rôle
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'enseignant') {
    header('Location: ../login.php');
    exit();
}

$emploiDuTemps = new EmploiDuTemps($pdo);
$signature = new Signature($pdo);
$user = $_SESSION['user'];

// Récupération de l'emploi du temps de l'enseignant
$emploiDuTempsEnseignant = $emploiDuTemps->getEmploiDuTempsByEnseignant($user['id']);

// Récupération des matières enseignées
$matieres = $emploiDuTemps->getMatieresByEnseignant($user['id']);

// Récupération des signatures du jour
$signaturesDuJour = $signature->getSignaturesByMatiere(
    $_GET['matiere'] ?? null,
    date('Y-m-d')
);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Enseignant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Espace Enseignant</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Tableau de bord</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="signatures.php">Gestion des présences</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="emploi_du_temps.php">Emploi du temps</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Déconnexion</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Tableau de bord</h2>

        <!-- Prochains cours -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Prochains cours</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Heure</th>
                                <th>Matière</th>
                                <th>Classe</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emploiDuTempsEnseignant as $cours): ?>
                                <tr>
                                    <td><?php echo $cours['jour']; ?></td>
                                    <td><?php echo date('H:i', strtotime($cours['heure'])); ?></td>
                                    <td><?php echo htmlspecialchars($cours['matiere_nom']); ?></td>
                                    <td><?php echo htmlspecialchars($cours['classe_nom']); ?></td>
                                    <td>
                                        <a href="signatures.php?matiere=<?php echo $cours['id_matiere']; ?>"
                                            class="btn btn-sm btn-primary">
                                            Gérer les présences
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Signatures du jour -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Signatures du jour</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Heure</th>
                                <th>Élève</th>
                                <th>Matière</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($signaturesDuJour as $sig): ?>
                                <tr>
                                    <td><?php echo date('H:i', strtotime($sig['heure'])); ?></td>
                                    <td><?php echo htmlspecialchars($sig['prenom'] . ' ' . $sig['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($sig['matiere_nom']); ?></td>
                                    <td>
                                        <?php if (strtotime($sig['heure']) > strtotime(date('H:i:s'))): ?>
                                            <span class="badge bg-warning">En retard</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">À l'heure</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="voir_signature.php?id=<?php echo $sig['id']; ?>"
                                            class="btn btn-sm btn-info">
                                            Voir
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>