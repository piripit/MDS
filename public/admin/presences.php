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

// Filtres
$date_debut = $_GET['date_debut'] ?? date('Y-m-d');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$classe_id = $_GET['classe'] ?? '';
$enseignant_id = $_GET['enseignant'] ?? '';
$statut_filtre = $_GET['statut'] ?? '';

// Récupération des données pour les filtres
try {
    // Classes
    $stmt = $pdo->query("SELECT id, nom, niveau FROM classes ORDER BY niveau, nom");
    $classes = $stmt->fetchAll();

    // Enseignants
    $stmt = $pdo->query("SELECT id, nom, prenom FROM utilisateurs WHERE role = 'enseignant' ORDER BY nom, prenom");
    $enseignants = $stmt->fetchAll();

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

    // Signatures selon les filtres
    $where_conditions = ["s.date_signature >= ? AND s.date_signature <= ?"];
    $params = [$date_debut, $date_fin];

    if ($classe_id) {
        $where_conditions[] = "c.id = ?";
        $params[] = $classe_id;
    }
    if ($enseignant_id) {
        $where_conditions[] = "s.id_enseignant = ?";
        $params[] = $enseignant_id;
    }
    if ($statut_filtre) {
        $where_conditions[] = "s.statut = ?";
        $params[] = $statut_filtre;
    }

    $where_clause = implode(' AND ', $where_conditions);

    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            u.nom,
            u.prenom,
            u.email,
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
        WHERE $where_clause
        ORDER BY s.date_signature DESC, s.heure_signature DESC
    ");
    $stmt->execute($params);
    $signatures = $stmt->fetchAll();

    // Statistiques pour la période
    $stmt = $pdo->prepare("
        SELECT 
            statut,
            COUNT(*) as count
        FROM signatures s
        INNER JOIN matieres m ON s.id_matiere = m.id
        INNER JOIN classes c ON m.id_classe = c.id
        WHERE $where_clause
        GROUP BY statut
    ");
    $stmt->execute($params);
    $stats_periode = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Historique des sessions
    $stmt = $pdo->prepare("
        SELECT 
            ss.*,
            m.nom as matiere_nom,
            u.nom as enseignant_nom,
            u.prenom as enseignant_prenom,
            c.nom as classe_nom,
            c.niveau as classe_niveau,
            COUNT(s.id) as nb_signatures
        FROM sessions_signature ss
        INNER JOIN matieres m ON ss.id_matiere = m.id
        INNER JOIN utilisateurs u ON ss.id_enseignant = u.id
        INNER JOIN classes c ON ss.id_classe = c.id
        LEFT JOIN signatures s ON ss.id_matiere = s.id_matiere 
            AND ss.id_enseignant = s.id_enseignant 
            AND ss.date_session = s.date_signature
        WHERE ss.date_session >= ? AND ss.date_session <= ?
        GROUP BY ss.id
        ORDER BY ss.date_session DESC, ss.heure_debut DESC
    ");
    $stmt->execute([$date_debut, $date_fin]);
    $sessions_historique = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des données: " . $e->getMessage());
    $classes = $enseignants = $sessions_actives = $signatures = $sessions_historique = [];
    $stats_periode = [];
}

// Organiser les statistiques
$stats = [
    'present' => $stats_periode['present'] ?? 0,
    'absent' => $stats_periode['absent'] ?? 0,
    'retard' => $stats_periode['retard'] ?? 0
];
$total_signatures = array_sum($stats);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Présences - Administration</title>
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
                        <a class="nav-link" href="dashboard.php">Tableau de bord</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="presences.php">
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
        <h2><i class="bi bi-person-check"></i> Gestion des Présences</h2>

        <!-- Sessions actives en temps réel -->
        <?php if (!empty($sessions_actives)): ?>
            <div class="alert alert-info">
                <h5><i class="bi bi-broadcast-pin"></i> Sessions de signature actives maintenant</h5>
                <div class="row">
                    <?php foreach ($sessions_actives as $session): ?>
                        <div class="col-md-4 mb-2">
                            <div class="border rounded p-2">
                                <strong><?php echo htmlspecialchars($session['matiere_nom']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($session['enseignant_prenom'] . ' ' . $session['enseignant_nom']); ?>
                                    • <?php echo htmlspecialchars($session['classe_niveau'] . ' ' . $session['classe_nom']); ?>
                                    • Depuis <?php echo substr($session['heure_debut'], 0, 5); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filtres -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Filtres et recherche</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="date_debut" class="form-label">Date début</label>
                        <input type="date" class="form-control" name="date_debut" value="<?php echo $date_debut; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_fin" class="form-label">Date fin</label>
                        <input type="date" class="form-control" name="date_fin" value="<?php echo $date_fin; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="classe" class="form-label">Classe</label>
                        <select class="form-select" name="classe">
                            <option value="">Toutes les classes</option>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>"
                                    <?php echo $classe_id == $classe['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($classe['niveau'] . ' ' . $classe['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="enseignant" class="form-label">Enseignant</label>
                        <select class="form-select" name="enseignant">
                            <option value="">Tous les enseignants</option>
                            <?php foreach ($enseignants as $enseignant): ?>
                                <option value="<?php echo $enseignant['id']; ?>"
                                    <?php echo $enseignant_id == $enseignant['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" name="statut">
                            <option value="">Tous les statuts</option>
                            <option value="present" <?php echo $statut_filtre == 'present' ? 'selected' : ''; ?>>Présent</option>
                            <option value="retard" <?php echo $statut_filtre == 'retard' ? 'selected' : ''; ?>>Retard</option>
                            <option value="absent" <?php echo $statut_filtre == 'absent' ? 'selected' : ''; ?>>Absent</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filtrer
                        </button>
                        <a href="presences.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Réinitialiser
                        </a>
                        <a href="?export=excel&<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                            <i class="bi bi-file-excel"></i> Exporter Excel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistiques de la période -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center bg-success text-white">
                    <div class="card-body">
                        <h3><?php echo $stats['present']; ?></h3>
                        <p class="mb-0">Présents</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-warning text-white">
                    <div class="card-body">
                        <h3><?php echo $stats['retard']; ?></h3>
                        <p class="mb-0">Retards</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-danger text-white">
                    <div class="card-body">
                        <h3><?php echo $stats['absent']; ?></h3>
                        <p class="mb-0">Absents</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-primary text-white">
                    <div class="card-body">
                        <h3><?php echo $total_signatures; ?></h3>
                        <p class="mb-0">Total signatures</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglets -->
        <ul class="nav nav-tabs" id="presenceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="signatures-tab" data-bs-toggle="tab" data-bs-target="#signatures" type="button">
                    <i class="bi bi-list-check"></i> Signatures (<?php echo count($signatures); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="sessions-tab" data-bs-toggle="tab" data-bs-target="#sessions" type="button">
                    <i class="bi bi-broadcast"></i> Sessions (<?php echo count($sessions_historique); ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="presenceTabContent">
            <!-- Onglet Signatures -->
            <div class="tab-pane fade show active" id="signatures" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($signatures)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1"></i>
                                <p class="mt-2">Aucune signature trouvée pour cette période</p>
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
                                            <th>Commentaire</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($signatures as $signature): ?>
                                            <tr>
                                                <td>
                                                    <small>
                                                        <?php echo date('d/m/Y', strtotime($signature['date_signature'])); ?>
                                                        <br>
                                                        <strong><?php echo substr($signature['heure_signature'], 0, 5); ?></strong>
                                                    </small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($signature['prenom'] . ' ' . $signature['nom']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($signature['email']); ?></small>
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
                                                <td>
                                                    <small><?php echo $signature['commentaire'] ? htmlspecialchars($signature['commentaire']) : '-'; ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Onglet Sessions -->
            <div class="tab-pane fade" id="sessions" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($sessions_historique)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-broadcast-pin fs-1"></i>
                                <p class="mt-2">Aucune session trouvée pour cette période</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Matière</th>
                                            <th>Enseignant</th>
                                            <th>Classe</th>
                                            <th>Durée prévue</th>
                                            <th>Heure début</th>
                                            <th>Heure fin</th>
                                            <th>Statut</th>
                                            <th>Signatures</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions_historique as $session): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($session['date_session'])); ?></td>
                                                <td><?php echo htmlspecialchars($session['matiere_nom']); ?></td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($session['enseignant_prenom'] . ' ' . $session['enseignant_nom']); ?></small>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($session['classe_niveau'] . ' ' . $session['classe_nom']); ?></small>
                                                </td>
                                                <td><?php echo $session['duree_minutes']; ?> min</td>
                                                <td><?php echo substr($session['heure_debut'], 0, 5); ?></td>
                                                <td><?php echo $session['heure_fin'] ? substr($session['heure_fin'], 0, 5) : '-'; ?></td>
                                                <td>
                                                    <span class="badge <?php echo $session['statut'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $session['statut'] == 'active' ? 'Active' : 'Fermée'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $session['nb_signatures']; ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>