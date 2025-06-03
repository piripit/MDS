<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';
require_once '../../classes/EmploiDuTemps.php';

session_start();

// Vérification de l'authentification et du rôle
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'eleve') {
    header('Location: ../login.php');
    exit();
}

$emploiDuTemps = new EmploiDuTemps($pdo);
$user = $_SESSION['user'];

// Récupération de la classe de l'élève
try {
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM affectations_eleves ae
        INNER JOIN classes c ON ae.id_classe = c.id
        WHERE ae.id_eleve = ?
        AND ae.annee_scolaire = ?
        LIMIT 1
    ");
    $annee_scolaire = date('Y') . '-' . (date('Y') + 1);
    $stmt->execute([$user['id'], $annee_scolaire]);
    $classe = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération de la classe: " . $e->getMessage());
    $classe = null;
}

// Récupération de l'emploi du temps de la classe
$emploiDuTempsClasse = [];
if ($classe) {
    $emploiDuTempsClasse = $emploiDuTemps->getEmploiDuTempsByClasse($classe['id']);
}

// Organisation des cours par jour
$coursParJour = [
    1 => [], // Lundi
    2 => [], // Mardi
    3 => [], // Mercredi
    4 => [], // Jeudi
    5 => []  // Vendredi
];

foreach ($emploiDuTempsClasse as $cours) {
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
    <title>Mon Emploi du temps - Élève</title>
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

        .cours-enseignant {
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
                        <a class="nav-link active" href="emploi_du_temps.php">
                            <i class="bi bi-calendar-week"></i> Emploi du temps
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="presences.php">Mes présences</a>
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
            <?php if ($classe): ?>
                <div class="badge bg-primary fs-6">
                    Classe : <?php echo htmlspecialchars($classe['niveau'] . ' ' . $classe['nom']); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$classe): ?>
            <div class="alert alert-warning">
                <h4>Aucune classe assignée</h4>
                <p class="mb-0">Vous n'êtes pas encore affecté à une classe pour l'année scolaire <?php echo date('Y') . '-' . (date('Y') + 1); ?>.</p>
                <p class="mb-0">Veuillez contacter l'administration.</p>
            </div>
        <?php elseif (empty($emploiDuTempsClasse)): ?>
            <div class="alert alert-info">
                <h4>Emploi du temps en cours de création</h4>
                <p class="mb-0">L'emploi du temps de votre classe n'est pas encore disponible.</p>
                <p class="mb-0">Il sera publié prochainement par l'administration.</p>
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
                                                        <div class="cours-enseignant">
                                                            <?php echo htmlspecialchars($cours['enseignant_prenom'] . ' ' . $cours['enseignant_nom']); ?>
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
                                    <div class="text-muted">
                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($cours['enseignant_prenom'] . ' ' . $cours['enseignant_nom']); ?>
                                    </div>
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
                            <h3 class="text-primary"><?php echo count($emploiDuTempsClasse); ?></h3>
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
                                $matieres_uniques = array_unique(array_column($emploiDuTempsClasse, 'matiere_nom'));
                                echo count($matieres_uniques);
                                ?>
                            </h3>
                            <p class="card-text">matières différentes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title">Enseignants</h5>
                            <h3 class="text-success">
                                <?php
                                $enseignants_uniques = array_unique(array_map(function ($cours) {
                                    return $cours['enseignant_nom'] . ' ' . $cours['enseignant_prenom'];
                                }, $emploiDuTempsClasse));
                                echo count($enseignants_uniques);
                                ?>
                            </h3>
                            <p class="card-text">professeurs</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>