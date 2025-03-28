<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';
require_once '../../classes/EmploiDuTemps.php';

session_start();

// Vérification de l'authentification et du rôle
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$emploiDuTemps = new EmploiDuTemps($pdo);

// Récupération des statistiques
try {
    // Nombre d'élèves
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM utilisateurs WHERE role = 'eleve'");
    $nbEleves = $stmt->fetch()['count'];

    // Nombre d'enseignants
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM utilisateurs WHERE role = 'enseignant'");
    $nbEnseignants = $stmt->fetch()['count'];

    // Nombre de classes
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM classes");
    $nbClasses = $stmt->fetch()['count'];

    // Nombre de matières
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM matieres");
    $nbMatieres = $stmt->fetch()['count'];

    // Dernières absences
    $stmt = $pdo->query("
        SELECT s.*, u.nom, u.prenom, m.nom as matiere_nom, c.nom as classe_nom
        FROM signatures s
        JOIN utilisateurs u ON s.id_utilisateur = u.id
        JOIN matieres m ON s.id_matiere = m.id
        JOIN classes c ON m.id_classe = c.id
        ORDER BY s.date DESC, s.heure DESC
        LIMIT 5
    ");
    $dernieresAbsences = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Administration</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Tableau de bord</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="eleves.php">Élèves</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="enseignants.php">Enseignants</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="classes.php">Classes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="matieres.php">Matières</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="emploi_du_temps.php">
                            <i class="bi bi-calendar-week"></i> Emploi du temps
                        </a>
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

        <!-- Statistiques -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Élèves</h5>
                        <p class="card-text display-4"><?php echo $nbEleves; ?></p>
                        <a href="eleves.php" class="text-white">Gérer les élèves →</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Enseignants</h5>
                        <p class="card-text display-4"><?php echo $nbEnseignants; ?></p>
                        <a href="enseignants.php" class="text-white">Gérer les enseignants →</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Classes</h5>
                        <p class="card-text display-4"><?php echo $nbClasses; ?></p>
                        <a href="classes.php" class="text-white">Gérer les classes →</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Matières</h5>
                        <p class="card-text display-4"><?php echo $nbMatieres; ?></p>
                        <a href="matieres.php" class="text-white">Gérer les matières →</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dernières absences -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Dernières absences</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Élève</th>
                                <th>Matière</th>
                                <th>Classe</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dernieresAbsences as $absence): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($absence['date'] . ' ' . $absence['heure'])); ?></td>
                                    <td><?php echo htmlspecialchars($absence['prenom'] . ' ' . $absence['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($absence['matiere_nom']); ?></td>
                                    <td><?php echo htmlspecialchars($absence['classe_nom']); ?></td>
                                    <td>
                                        <a href="voir_absence.php?id=<?php echo $absence['id']; ?>" class="btn btn-sm btn-info">
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