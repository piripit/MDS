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

// Récupération de la matière sélectionnée
$id_matiere = $_GET['matiere'] ?? null;
$date = $_GET['date'] ?? date('Y-m-d');

// Récupération des matières de l'enseignant
$matieres = $emploiDuTemps->getMatieresByEnseignant($user['id']);

// Récupération des sessions actives
$sessionsActives = $signature->getSessionsActives($user['id']);

// Variables pour la matière sélectionnée
$eleves = [];
$signatures = [];
$sessionActive = false;
$classe_id = null;

if ($id_matiere) {
    // Récupération des élèves de la classe pour cette matière
    $eleves = $signature->getElevesClasse($id_matiere);

    // Récupération des signatures existantes
    $signatures = $signature->getSignaturesByMatiere($id_matiere, $date);

    // Vérifier si une session est active pour cette matière
    $sessionActive = $signature->sessionActive($id_matiere, $user['id']);

    // Récupérer l'ID de la classe pour cette matière
    if (!empty($eleves)) {
        $classe_id = $eleves[0]['classe_nom']; // Simplification, on prend la première classe
    }
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'ouvrir_session':
                $duree = $_POST['duree_minutes'] ?? 15;

                // Récupérer l'ID de la classe pour cette matière
                $stmt = $pdo->prepare("SELECT id_classe FROM matieres WHERE id = ?");
                $stmt->execute([$id_matiere]);
                $matiere_info = $stmt->fetch();

                if (!$matiere_info) {
                    throw new Exception("Matière non trouvée");
                }

                $signature->ouvrirSession($id_matiere, $user['id'], $matiere_info['id_classe'], $duree);
                $_SESSION['success'] = "Session de signature ouverte pour $duree minutes";
                break;

            case 'fermer_session':
                $absents_marques = $signature->marquerAbsents($id_matiere, $user['id'], $classe_id);
                $signature->fermerSession($id_matiere, $user['id']);
                $_SESSION['success'] = "Session fermée. $absents_marques élève(s) marqué(s) absent(s) automatiquement";
                break;

            case 'modifier_statut':
                $id_signature = $_POST['id_signature'];
                $nouveau_statut = $_POST['statut'];
                $retard_minutes = $_POST['retard_minutes'] ?? null;
                $commentaire = $_POST['commentaire'] ?? null;

                $signature->modifierStatutSignature($id_signature, $nouveau_statut, $retard_minutes, $commentaire);
                $_SESSION['success'] = "Statut modifié avec succès";
                break;

            case 'ajouter_signature_manuelle':
                $id_eleve = $_POST['id_eleve'];
                $statut = $_POST['statut'];
                $retard_minutes = $_POST['retard_minutes'] ?? null;
                $commentaire = $_POST['commentaire'] ?? 'Ajouté manuellement par l\'enseignant';

                $signature->enregistrerSignature($id_eleve, $id_matiere, $user['id'], $statut, $retard_minutes, $commentaire);
                $_SESSION['success'] = "Signature ajoutée manuellement";
                break;
        }

        header("Location: gestion_presences.php?matiere=$id_matiere&date=$date");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Organiser les signatures par élève
$signatures_par_eleve = [];
foreach ($signatures as $sig) {
    $signatures_par_eleve[$sig['id_eleve']] = $sig;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Présences - Enseignant</title>
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
                        <a class="nav-link" href="dashboard.php">Tableau de bord</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="gestion_presences.php">Gestion des présences</a>
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
        <h2><i class="bi bi-person-check"></i> Gestion des Présences</h2>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error'];
                unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Sessions actives -->
        <?php if (!empty($sessionsActives)): ?>
            <div class="alert alert-info">
                <h5><i class="bi bi-broadcast"></i> Sessions actives</h5>
                <?php foreach ($sessionsActives as $session): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>
                            <strong><?php echo htmlspecialchars($session['matiere_nom']); ?></strong>
                            (<?php echo htmlspecialchars($session['classe_niveau'] . ' ' . $session['classe_nom']); ?>)
                            - Depuis <?php echo substr($session['heure_debut'], 0, 5); ?>
                        </span>
                        <a href="?matiere=<?php echo $session['id_matiere']; ?>" class="btn btn-sm btn-primary">
                            Gérer
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Sélection matière et date -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label for="matiere" class="form-label">Matière</label>
                        <select class="form-select" name="matiere" id="matiere" required>
                            <option value="">Sélectionnez une matière</option>
                            <?php foreach ($matieres as $matiere): ?>
                                <option value="<?php echo $matiere['id']; ?>"
                                    <?php echo $id_matiere == $matiere['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($matiere['nom']); ?>
                                    (<?php echo htmlspecialchars($matiere['classe_niveau'] . ' ' . $matiere['classe_nom']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" class="form-control" name="date" id="date"
                            value="<?php echo $date; ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100">Filtrer</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($id_matiere): ?>
            <!-- Contrôles de session -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-broadcast"></i> Contrôle de la session de signature
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($sessionActive): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-broadcast-pin"></i>
                            <strong>Session active !</strong>
                            Les élèves peuvent signer leur présence depuis <?php echo substr($sessionActive['heure_debut'], 0, 5); ?>
                            (durée : <?php echo $sessionActive['duree_minutes']; ?> minutes)
                        </div>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="fermer_session">
                            <button type="submit" class="btn btn-danger"
                                onclick="return confirm('Fermer la session ? Les élèves qui n\'ont pas signé seront marqués absents.')">
                                <i class="bi bi-stop-circle"></i> Fermer la session
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-pause-circle"></i>
                            <strong>Aucune session active.</strong>
                            Les élèves ne peuvent pas signer leur présence.
                        </div>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="ouvrir_session">
                            <div class="col-md-3">
                                <label for="duree_minutes" class="form-label">Durée (minutes)</label>
                                <select class="form-select" name="duree_minutes" id="duree_minutes">
                                    <option value="10">10 minutes</option>
                                    <option value="15" selected>15 minutes</option>
                                    <option value="20">20 minutes</option>
                                    <option value="30">30 minutes</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-play-circle"></i> Ouvrir la session
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Liste des élèves -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-people"></i>
                        Liste des élèves (<?php echo count($eleves); ?> élève<?php echo count($eleves) > 1 ? 's' : ''; ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($eleves)): ?>
                        <div class="alert alert-warning">
                            Aucun élève trouvé pour cette matière.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Élève</th>
                                        <th>Statut</th>
                                        <th>Heure</th>
                                        <th>Retard</th>
                                        <th>Commentaire</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($eleves as $eleve): ?>
                                        <?php $signature_eleve = $signatures_par_eleve[$eleve['id']] ?? null; ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($signature_eleve): ?>
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
                                                    <span class="badge <?php echo $statut_class[$signature_eleve['statut']]; ?>">
                                                        <?php echo $statut_text[$signature_eleve['statut']]; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non marqué</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $signature_eleve ? substr($signature_eleve['heure_signature'], 0, 5) : '-'; ?>
                                            </td>
                                            <td>
                                                <?php echo $signature_eleve && $signature_eleve['retard_minutes'] ? $signature_eleve['retard_minutes'] . ' min' : '-'; ?>
                                            </td>
                                            <td>
                                                <?php echo $signature_eleve ? htmlspecialchars($signature_eleve['commentaire']) : '-'; ?>
                                            </td>
                                            <td>
                                                <?php if ($signature_eleve): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                        data-bs-toggle="modal" data-bs-target="#editModal<?php echo $eleve['id']; ?>">
                                                        <i class="bi bi-pencil"></i> Modifier
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success"
                                                        data-bs-toggle="modal" data-bs-target="#addModal<?php echo $eleve['id']; ?>">
                                                        <i class="bi bi-plus"></i> Ajouter
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <!-- Modal modification -->
                                        <?php if ($signature_eleve): ?>
                                            <div class="modal fade" id="editModal<?php echo $eleve['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Modifier le statut</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="action" value="modifier_statut">
                                                                <input type="hidden" name="id_signature" value="<?php echo $signature_eleve['id']; ?>">

                                                                <p><strong><?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']); ?></strong></p>

                                                                <div class="mb-3">
                                                                    <label class="form-label">Statut</label>
                                                                    <select class="form-select" name="statut" required>
                                                                        <option value="present" <?php echo $signature_eleve['statut'] == 'present' ? 'selected' : ''; ?>>Présent</option>
                                                                        <option value="retard" <?php echo $signature_eleve['statut'] == 'retard' ? 'selected' : ''; ?>>Retard</option>
                                                                        <option value="absent" <?php echo $signature_eleve['statut'] == 'absent' ? 'selected' : ''; ?>>Absent</option>
                                                                    </select>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label">Retard (minutes)</label>
                                                                    <input type="number" class="form-control" name="retard_minutes"
                                                                        value="<?php echo $signature_eleve['retard_minutes']; ?>" min="0">
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label">Commentaire</label>
                                                                    <textarea class="form-control" name="commentaire" rows="2"><?php echo htmlspecialchars($signature_eleve['commentaire']); ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                <button type="submit" class="btn btn-primary">Modifier</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Modal ajout -->
                                        <?php if (!$signature_eleve): ?>
                                            <div class="modal fade" id="addModal<?php echo $eleve['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Ajouter une signature</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="action" value="ajouter_signature_manuelle">
                                                                <input type="hidden" name="id_eleve" value="<?php echo $eleve['id']; ?>">

                                                                <p><strong><?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']); ?></strong></p>

                                                                <div class="mb-3">
                                                                    <label class="form-label">Statut</label>
                                                                    <select class="form-select" name="statut" required>
                                                                        <option value="present">Présent</option>
                                                                        <option value="retard">Retard</option>
                                                                        <option value="absent">Absent</option>
                                                                    </select>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label">Retard (minutes)</label>
                                                                    <input type="number" class="form-control" name="retard_minutes" min="0">
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label">Commentaire</label>
                                                                    <textarea class="form-control" name="commentaire" rows="2" placeholder="Ajouté manuellement par l'enseignant"></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                <button type="submit" class="btn btn-success">Ajouter</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Statistiques -->
                        <div class="row mt-3">
                            <?php
                            $stats = [
                                'total' => count($eleves),
                                'presents' => 0,
                                'retards' => 0,
                                'absents' => 0,
                                'non_marques' => 0
                            ];

                            foreach ($eleves as $eleve) {
                                $sig = $signatures_par_eleve[$eleve['id']] ?? null;
                                if ($sig) {
                                    $stats[$sig['statut'] == 'present' ? 'presents' : ($sig['statut'] == 'retard' ? 'retards' : 'absents')]++;
                                } else {
                                    $stats['non_marques']++;
                                }
                            }
                            ?>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="fs-4 text-success"><?php echo $stats['presents']; ?></div>
                                    <div>Présents</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="fs-4 text-warning"><?php echo $stats['retards']; ?></div>
                                    <div>Retards</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="fs-4 text-danger"><?php echo $stats['absents']; ?></div>
                                    <div>Absents</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="fs-4 text-secondary"><?php echo $stats['non_marques']; ?></div>
                                    <div>Non marqués</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>