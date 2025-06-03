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

// Récupérer les cours d'aujourd'hui
$jourActuel = date('N'); // 1=Lundi, 2=Mardi, etc.
$coursAujourdhui = array_filter($emploiDuTempsEnseignant, function ($cours) use ($jourActuel) {
    return $cours['jour'] == $jourActuel;
});

// Trier par heure
usort($coursAujourdhui, function ($a, $b) {
    return strcmp($a['heure_debut'], $b['heure_debut']);
});

// Récupération des signatures du jour
$signaturesDuJour = [];
foreach ($matieres as $matiere) {
    $sigs = $signature->getSignaturesByMatiere($matiere['id'], date('Y-m-d'));
    $signaturesDuJour = array_merge($signaturesDuJour, $sigs);
}

$joursNoms = [
    1 => 'Lundi',
    2 => 'Mardi',
    3 => 'Mercredi',
    4 => 'Jeudi',
    5 => 'Vendredi',
    6 => 'Samedi',
    7 => 'Dimanche'
];
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
                        <a class="nav-link" href="gestion_presences.php">Gestion des présences</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="emploi_du_temps.php">Emploi du temps</a>
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
        <div class="row">
            <div class="col-md-8">
                <h2>Bonjour <?php echo htmlspecialchars($user['prenom']); ?> ! 👋</h2>
                <p class="text-muted">Votre tableau de bord enseignant pour le <?php echo date('d/m/Y'); ?></p>
            </div>
            <div class="col-md-4 text-end">
                <div class="badge bg-primary fs-6 p-2">
                    <i class="bi bi-person-badge"></i>
                    Enseignant
                </div>
            </div>
        </div>

        <!-- Statistiques rapides -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <i class="bi bi-calendar-week text-primary fs-1"></i>
                        <h5 class="mt-2">Cours aujourd'hui</h5>
                        <h3 class="text-primary"><?php echo count($coursAujourdhui); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <i class="bi bi-book text-info fs-1"></i>
                        <h5 class="mt-2">Matières</h5>
                        <h3 class="text-info"><?php echo count($matieres); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <i class="bi bi-person-check text-success fs-1"></i>
                        <h5 class="mt-2">Signatures</h5>
                        <h3 class="text-success"><?php echo count($signaturesDuJour); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <i class="bi bi-clock text-warning fs-1"></i>
                        <h5 class="mt-2">Total cours</h5>
                        <h3 class="text-warning"><?php echo count($emploiDuTempsEnseignant); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cours d'aujourd'hui -->
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-calendar-day"></i>
                    Mes cours aujourd'hui (<?php echo $joursNoms[$jourActuel]; ?> <?php echo date('d/m/Y'); ?>)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($coursAujourdhui)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Aucun cours programmé aujourd'hui ! 🎉
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-clock"></i> Heure</th>
                                    <th><i class="bi bi-book"></i> Matière</th>
                                    <th><i class="bi bi-mortarboard"></i> Classe</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $heureActuelle = date('H:i');
                                foreach ($coursAujourdhui as $cours):
                                    $heure_debut = substr($cours['heure_debut'], 0, 5);
                                    $heure_fin = substr($cours['heure_fin'], 0, 5);

                                    // Déterminer le statut du cours
                                    $statut = '';
                                    $statut_class = '';
                                    if ($heureActuelle < $heure_debut) {
                                        $statut = 'À venir';
                                        $statut_class = 'bg-secondary';
                                    } elseif ($heureActuelle >= $heure_debut && $heureActuelle <= $heure_fin) {
                                        $statut = 'En cours';
                                        $statut_class = 'bg-success';
                                    } else {
                                        $statut = 'Terminé';
                                        $statut_class = 'bg-danger';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $heure_debut; ?> - <?php echo $heure_fin; ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($cours['matiere_nom']); ?></span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($cours['classe_niveau'] . ' ' . $cours['classe_nom']); ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $statut_class; ?>"><?php echo $statut; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($statut === 'En cours' || $statut === 'À venir'): ?>
                                                <a href="gestion_presences.php?matiere=<?php echo $cours['id_matiere']; ?>&date=<?php echo date('Y-m-d'); ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-person-check"></i> Gérer présences
                                                </a>
                                            <?php else: ?>
                                                <a href="gestion_presences.php?matiere=<?php echo $cours['id_matiere']; ?>&date=<?php echo date('Y-m-d'); ?>"
                                                    class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-eye"></i> Voir présences
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Accès rapides -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-person-check"></i> Gestion des présences</h5>
                    </div>
                    <div class="card-body">
                        <p>Gérez les présences de vos élèves pour chaque cours.</p>
                        <a href="gestion_presences.php" class="btn btn-primary">
                            <i class="bi bi-person-check"></i> Gérer les présences
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-calendar-week"></i> Emploi du temps</h5>
                    </div>
                    <div class="card-body">
                        <p>Consultez votre emploi du temps complet de la semaine.</p>
                        <a href="emploi_du_temps.php" class="btn btn-outline-primary">
                            <i class="bi bi-calendar-week"></i> Voir l'emploi du temps
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mes matières -->
        <?php if (!empty($matieres)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-book"></i> Mes matières</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($matieres as $matiere): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card border-left-primary">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($matiere['nom']); ?></h6>
                                        <p class="card-text text-muted">
                                            <?php echo htmlspecialchars($matiere['classe_niveau'] . ' ' . $matiere['classe_nom']); ?>
                                        </p>
                                        <a href="gestion_presences.php?matiere=<?php echo $matiere['id']; ?>"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-person-check"></i> Voir présences
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Informations -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-info-circle"></i> Informations</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="bi bi-clock"></i> Heure actuelle</h6>
                        <p id="heure-actuelle" class="fs-4 text-primary"><?php echo date('H:i:s'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-calendar3"></i> Date</h6>
                        <p class="fs-5"><?php echo date('l j F Y'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mise à jour de l'heure en temps réel
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR');
            document.getElementById('heure-actuelle').textContent = timeString;
        }

        // Mettre à jour l'heure toutes les secondes
        setInterval(updateTime, 1000);
    </script>
</body>

</html>