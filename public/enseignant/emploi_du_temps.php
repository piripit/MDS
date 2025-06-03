<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';
require_once '../../classes/EmploiDuTemps.php';

session_start();

// Vérification de l'authentification et du rôle
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'enseignant') {
    header('Location: ../login.php');
    exit();
}

$emploiDuTemps = new EmploiDuTemps($pdo);
$user = $_SESSION['user'];

// Récupération de l'emploi du temps de l'enseignant
$emploiDuTempsEnseignant = $emploiDuTemps->getEmploiDuTempsByEnseignant($user['id']);

// Organisation des cours par jour
$coursParJour = [
    1 => [], // Lundi
    2 => [], // Mardi
    3 => [], // Mercredi
    4 => [], // Jeudi
    5 => []  // Vendredi
];

foreach ($emploiDuTempsEnseignant as $cours) {
    $coursParJour[$cours['jour']][] = $cours;
}

// Tri des cours par heure pour chaque jour
foreach ($coursParJour as $jour => $cours) {
    usort($coursParJour[$jour], function ($a, $b) {
        return strcmp($a['heure_debut'], $b['heure_debut']);
    });
}

$joursNoms = [
    1 => 'Lundi',
    2 => 'Mardi',
    3 => 'Mercredi',
    4 => 'Jeudi',
    5 => 'Vendredi'
];
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Emploi du temps - Enseignant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .emploi-container {
            overflow-x: auto;
        }

        .emploi-table {
            min-width: 800px;
        }

        .jour-header {
            background-color: #0d6efd;
            color: white;
            text-align: center;
            font-weight: bold;
            padding: 10px;
        }

        .cours-cell {
            border: 1px solid #dee2e6;
            padding: 8px;
            vertical-align: top;
            min-height: 60px;
        }

        .cours-item {
            background-color: #e3f2fd;
            border-radius: 5px;
            padding: 5px;
            margin-bottom: 5px;
            border-left: 4px solid #2196f3;
        }

        .cours-matiere {
            font-weight: bold;
            color: #1565c0;
        }

        .cours-classe {
            font-size: 0.9em;
            color: #666;
        }

        .cours-heure {
            font-size: 0.8em;
            color: #999;
        }
    </style>
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
                        <a class="nav-link" href="dashboard.php">Tableau de bord</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gestion_presences.php">Gestion des présences</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="emploi_du_temps.php">
                            <i class="bi bi-calendar-week"></i> Emploi du temps
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-calendar-week"></i> Mon Emploi du temps</h2>
            <div class="badge bg-primary fs-6">
                Enseignant : <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?>
            </div>
        </div>

        <?php if (empty($emploiDuTempsEnseignant)): ?>
            <div class="alert alert-info">
                <h4>Aucun cours assigné</h4>
                <p class="mb-0">Vous n'avez pas encore de cours assignés dans l'emploi du temps.</p>
                <p class="mb-0">Veuillez contacter l'administration.</p>
            </div>
        <?php else: ?>
            <!-- Vue en tableau -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Semaine du <?php echo date('d/m/Y', strtotime('monday this week')); ?></h5>
                </div>
                <div class="card-body p-0">
                    <div class="emploi-container">
                        <table class="table table-bordered mb-0 emploi-table">
                            <thead>
                                <tr>
                                    <?php foreach ($joursNoms as $jour => $nomJour): ?>
                                        <th class="jour-header"><?php echo $nomJour; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <?php foreach ($joursNoms as $jour => $nomJour): ?>
                                        <td class="cours-cell">
                                            <?php if (!empty($coursParJour[$jour])): ?>
                                                <?php foreach ($coursParJour[$jour] as $cours): ?>
                                                    <div class="cours-item">
                                                        <div class="cours-heure">
                                                            <?php echo substr($cours['heure_debut'], 0, 5); ?> - <?php echo substr($cours['heure_fin'], 0, 5); ?>
                                                        </div>
                                                        <div class="cours-matiere">
                                                            <?php echo htmlspecialchars($cours['matiere_nom']); ?>
                                                        </div>
                                                        <div class="cours-classe">
                                                            <?php echo htmlspecialchars($cours['classe_niveau'] . ' ' . $cours['classe_nom']); ?>
                                                        </div>
                                                        <div class="mt-2">
                                                            <a href="gestion_presences.php?matiere=<?php echo $cours['id_matiere']; ?>&date=<?php echo date('Y-m-d'); ?>"
                                                                class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-person-check"></i> Présences
                                                            </a>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="text-muted text-center">Pas de cours</div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Vue en liste pour mobile -->
            <div class="card mt-4 d-md-none">
                <div class="card-header">
                    <h5 class="card-title mb-0">Planning détaillé</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($joursNoms as $jour => $nomJour): ?>
                        <h6 class="text-primary mt-3 mb-2"><?php echo $nomJour; ?></h6>
                        <?php if (!empty($coursParJour[$jour])): ?>
                            <?php foreach ($coursParJour[$jour] as $cours): ?>
                                <div class="border rounded p-3 mb-2">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <strong><?php echo htmlspecialchars($cours['matiere_nom']); ?></strong>
                                        <span class="badge bg-secondary">
                                            <?php echo substr($cours['heure_debut'], 0, 5); ?> - <?php echo substr($cours['heure_fin'], 0, 5); ?>
                                        </span>
                                    </div>
                                    <div class="text-muted mb-2">
                                        <i class="bi bi-mortarboard"></i> <?php echo htmlspecialchars($cours['classe_niveau'] . ' ' . $cours['classe_nom']); ?>
                                    </div>
                                    <a href="gestion_presences.php?matiere=<?php echo $cours['id_matiere']; ?>&date=<?php echo date('Y-m-d'); ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-person-check"></i> Gérer présences
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">Pas de cours ce jour</p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title">Total des cours</h5>
                            <h3 class="text-primary"><?php echo count($emploiDuTempsEnseignant); ?></h3>
                            <p class="card-text">cours par semaine</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title">Matières</h5>
                            <h3 class="text-info">
                                <?php
                                $matieres_uniques = array_unique(array_column($emploiDuTempsEnseignant, 'matiere_nom'));
                                echo count($matieres_uniques);
                                ?>
                            </h3>
                            <p class="card-text">matières enseignées</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title">Classes</h5>
                            <h3 class="text-success">
                                <?php
                                $classes_uniques = array_unique(array_map(function ($cours) {
                                    return $cours['classe_nom'];
                                }, $emploiDuTempsEnseignant));
                                echo count($classes_uniques);
                                ?>
                            </h3>
                            <p class="card-text">classes différentes</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cours du jour actuel -->
            <?php
            $jourActuel = date('N');
            $coursAujourdhui = array_filter($emploiDuTempsEnseignant, function ($cours) use ($jourActuel) {
                return $cours['jour'] == $jourActuel;
            });
            ?>
            <?php if (!empty($coursAujourdhui)): ?>
                <div class="card mt-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-day"></i>
                            Mes cours aujourd'hui (<?php echo $joursNoms[$jourActuel]; ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($coursAujourdhui as $cours): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border-left-success">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($cours['matiere_nom']); ?></h6>
                                            <p class="card-text">
                                                <strong>Horaire :</strong> <?php echo substr($cours['heure_debut'], 0, 5); ?> - <?php echo substr($cours['heure_fin'], 0, 5); ?><br>
                                                <strong>Classe :</strong> <?php echo htmlspecialchars($cours['classe_niveau'] . ' ' . $cours['classe_nom']); ?>
                                            </p>
                                            <a href="gestion_presences.php?matiere=<?php echo $cours['id_matiere']; ?>&date=<?php echo date('Y-m-d'); ?>"
                                                class="btn btn-success btn-sm">
                                                <i class="bi bi-person-check"></i> Gérer présences
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>