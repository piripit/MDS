<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';
require_once '../../classes/EmploiDuTemps.php';
require_once '../../classes/Signature.php';

session_start();

// Vérification de l'authentification et du rôle
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$emploiDuTemps = new EmploiDuTemps($pdo);
$signature = new Signature($pdo);

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

    // Statistiques des présences d'aujourd'hui
    $stmt = $pdo->prepare("
        SELECT 
            statut,
            COUNT(*) as count
        FROM signatures 
        WHERE date_signature = ?
        GROUP BY statut
    ");
    $stmt->execute([date('Y-m-d')]);
    $presences_aujourdhui = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Sessions actives actuellement
    $stmt = $pdo->query("
        SELECT 
            ss.*,
            m.nom as matiere_nom,
            u.nom as enseignant_nom,
            u.prenom as enseignant_prenom,
            c.nom as classe_nom,
            c.niveau as classe_niveau
        FROM sessions_signature ss
        INNER JOIN matieres m ON ss.id_matiere = m.id
        INNER JOIN utilisateurs u ON ss.id_enseignant = u.id
        INNER JOIN classes c ON ss.id_classe = c.id
        WHERE ss.statut = 'active' AND ss.date_session = CURDATE()
        ORDER BY ss.heure_debut DESC
    ");
    $sessions_actives = $stmt->fetchAll();

    // Dernières signatures (présences/absences/retards)
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            u.nom,
            u.prenom,
            m.nom as matiere_nom,
            ens.nom as enseignant_nom,
            ens.prenom as enseignant_prenom,
            c.nom as classe_nom,
            c.niveau as classe_niveau
        FROM signatures s
        INNER JOIN utilisateurs u ON s.id_eleve = u.id
        INNER JOIN matieres m ON s.id_matiere = m.id
        INNER JOIN utilisateurs ens ON s.id_enseignant = ens.id
        INNER JOIN classes c ON m.id_classe = c.id
        WHERE s.date_signature >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
        ORDER BY s.date_signature DESC, s.heure_signature DESC
        LIMIT 10
    ");
    $stmt->execute();
    $dernieresSignatures = $stmt->fetchAll();

    // Statistiques globales de la semaine
    $stmt = $pdo->prepare("
        SELECT 
            DATE(date_signature) as jour,
            statut,
            COUNT(*) as count
        FROM signatures 
        WHERE date_signature >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
        GROUP BY DATE(date_signature), statut
        ORDER BY jour DESC
    ");
    $stmt->execute();
    $stats_semaine = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
    $nbEleves = $nbEnseignants = $nbClasses = $nbMatieres = 0;
    $presences_aujourdhui = [];
    $sessions_actives = [];
    $dernieresSignatures = [];
    $stats_semaine = [];
}

// Organiser les statistiques
$stats_jour = [
    'present' => $presences_aujourdhui['present'] ?? 0,
    'absent' => $presences_aujourdhui['absent'] ?? 0,
    'retard' => $presences_aujourdhui['retard'] ?? 0
];
$total_jour = array_sum($stats_jour);
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
                        <a class="nav-link" href="presences.php">
                            <i class="bi bi-person-check"></i> Présences
                        </a>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Tableau de bord</h2>
            <div class="badge bg-primary fs-6">
                <?php echo date('l d F Y'); ?>
            </div>
        </div>

        <!-- Statistiques générales -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Élèves</h5>
                                <p class="card-text display-4"><?php echo $nbEleves; ?></p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people fs-1"></i>
                            </div>
                        </div>
                        <a href="eleves.php" class="text-white">Gérer les élèves →</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Enseignants</h5>
                                <p class="card-text display-4"><?php echo $nbEnseignants; ?></p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-person-badge fs-1"></i>
                            </div>
                        </div>
                        <a href="enseignants.php" class="text-white">Gérer les enseignants →</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Classes</h5>
                                <p class="card-text display-4"><?php echo $nbClasses; ?></p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-mortarboard fs-1"></i>
                            </div>
                        </div>
                        <a href="classes.php" class="text-white">Gérer les classes →</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Matières</h5>
                                <p class="card-text display-4"><?php echo $nbMatieres; ?></p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-book fs-1"></i>
                            </div>
                        </div>
                        <a href="matieres.php" class="text-white">Gérer les matières →</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques des présences d'aujourd'hui -->
        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-bar-chart"></i> Présences aujourd'hui (<?php echo date('d/m/Y'); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="fs-3 text-success"><?php echo $stats_jour['present']; ?></div>
                                <div class="text-muted">Présents</div>
                                <div class="progress mt-2">
                                    <div class="progress-bar bg-success" style="width: <?php echo $total_jour > 0 ? ($stats_jour['present'] / $total_jour * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="fs-3 text-warning"><?php echo $stats_jour['retard']; ?></div>
                                <div class="text-muted">Retards</div>
                                <div class="progress mt-2">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $total_jour > 0 ? ($stats_jour['retard'] / $total_jour * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="fs-3 text-danger"><?php echo $stats_jour['absent']; ?></div>
                                <div class="text-muted">Absents</div>
                                <div class="progress mt-2">
                                    <div class="progress-bar bg-danger" style="width: <?php echo $total_jour > 0 ? ($stats_jour['absent'] / $total_jour * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="fs-3 text-primary"><?php echo $total_jour; ?></div>
                                <div class="text-muted">Total signatures</div>
                                <a href="presences.php" class="btn btn-outline-primary btn-sm mt-2">
                                    Voir détails →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-broadcast"></i> Sessions actives
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sessions_actives)): ?>
                            <div class="text-muted text-center">
                                <i class="bi bi-pause-circle fs-2"></i>
                                <p class="mt-2">Aucune session active</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($sessions_actives as $session): ?>
                                <div class="border rounded p-2 mb-2">
                                    <div class="fw-bold"><?php echo htmlspecialchars($session['matiere_nom']); ?></div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($session['enseignant_prenom'] . ' ' . $session['enseignant_nom']); ?>
                                        <br>
                                        <?php echo htmlspecialchars($session['classe_niveau'] . ' ' . $session['classe_nom']); ?>
                                        <br>
                                        <i class="bi bi-clock"></i> Depuis <?php echo substr($session['heure_debut'], 0, 5); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                            <a href="presences.php" class="btn btn-primary btn-sm w-100">
                                Gérer les sessions →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dernières signatures -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock-history"></i> Dernières signatures
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($dernieresSignatures)): ?>
                    <div class="text-muted text-center">
                        Aucune signature récente
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date/Heure</th>
                                    <th>Élève</th>
                                    <th>Matière</th>
                                    <th>Enseignant</th>
                                    <th>Classe</th>
                                    <th>Statut</th>
                                    <th>Retard</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dernieresSignatures as $signature): ?>
                                    <tr>
                                        <td>
                                            <small>
                                                <?php echo date('d/m/Y', strtotime($signature['date_signature'])); ?>
                                                <br>
                                                <?php echo substr($signature['heure_signature'], 0, 5); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($signature['prenom'] . ' ' . $signature['nom']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($signature['matiere_nom']); ?></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($signature['enseignant_prenom'] . ' ' . $signature['enseignant_nom']); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($signature['classe_niveau'] . ' ' . $signature['classe_nom']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $statut_class = [
                                                'present' => 'bg-success',
                                                'retard' => 'bg-warning',
                                                'absent' => 'bg-danger'
                                            ];
                                            $statut_text = [
                                                'present' => 'Présent',
                                                'retard' => 'Retard',
                                                'absent' => 'Absent'
                                            ];
                                            ?>
                                            <span class="badge <?php echo $statut_class[$signature['statut']]; ?>">
                                                <?php echo $statut_text[$signature['statut']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $signature['retard_minutes'] ? $signature['retard_minutes'] . ' min' : '-'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center">
                        <a href="presences.php" class="btn btn-outline-primary">
                            Voir toutes les présences →
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>