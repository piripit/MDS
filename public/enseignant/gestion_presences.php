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

// Récupération des élèves de la classe
try {
    $stmt = $pdo->prepare("
        SELECT u.*, ae.id_classe
        FROM utilisateurs u
        JOIN affectations_eleves ae ON u.id = ae.id_eleve
        JOIN matieres m ON ae.id_classe = m.id_classe
        WHERE m.id = :id_matiere
        AND ae.annee_scolaire = :annee_scolaire
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute([
        ':id_matiere' => $id_matiere,
        ':annee_scolaire' => date('Y') . '-' . (date('Y') + 1)
    ]);
    $eleves = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des élèves: " . $e->getMessage());
    $eleves = [];
}

// Récupération des signatures du jour
$signatures = [];
if ($id_matiere) {
    $signatures = $signature->getSignaturesByMatiere($id_matiere, $date);
}

// Traitement de la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_eleve = $_POST['id_eleve'] ?? null;
    $statut = $_POST['statut'] ?? null;
    $retard = $_POST['retard'] ?? null;

    if ($id_eleve && $statut) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO signatures (id_utilisateur, id_matiere, date, heure, signature, horodatage)
                VALUES (:id_utilisateur, :id_matiere, :date, :heure, :signature, :horodatage)
            ");

            $horodatage = $signature->generateHorodatage();
            $signatureData = json_encode([
                'statut' => $statut,
                'retard' => $retard,
                'enseignant' => $user['id']
            ]);

            $stmt->execute([
                ':id_utilisateur' => $id_eleve,
                ':id_matiere' => $id_matiere,
                ':date' => $date,
                ':heure' => date('H:i:s'),
                ':signature' => $signatureData,
                ':horodatage' => json_encode($horodatage)
            ]);

            $_SESSION['success'] = "Présence enregistrée avec succès";
            header("Location: gestion_presences.php?matiere=$id_matiere&date=$date");
            exit();
        } catch (Exception $e) {
            error_log("Erreur lors de l'enregistrement de la présence: " . $e->getMessage());
            $_SESSION['error'] = "Erreur lors de l'enregistrement de la présence";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Présences - Enseignant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Déconnexion</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Gestion des Présences</h2>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error'];
                unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Filtres -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="matiere" class="form-label">Matière</label>
                        <select class="form-select" name="matiere" id="matiere" required>
                            <option value="">Sélectionnez une matière</option>
                            <?php foreach ($matieres as $matiere): ?>
                                <option value="<?php echo $matiere['id']; ?>"
                                    <?php echo $id_matiere == $matiere['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($matiere['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" class="form-control" name="date" id="date"
                            value="<?php echo $date; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block">Filtrer</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($id_matiere): ?>
            <!-- Liste des élèves -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Liste des élèves</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Statut</th>
                                    <th>Retard</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($eleves as $eleve): ?>
                                    <?php
                                    $signatureEleve = null;
                                    foreach ($signatures as $sig) {
                                        if ($sig['id_utilisateur'] == $eleve['id']) {
                                            $signatureEleve = $sig;
                                            break;
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($eleve['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($eleve['prenom']); ?></td>
                                        <td>
                                            <?php if ($signatureEleve): ?>
                                                <?php
                                                $statut = json_decode($signatureEleve['signature'], true);
                                                switch ($statut['statut']) {
                                                    case 'present':
                                                        echo '<span class="badge bg-success">Présent</span>';
                                                        break;
                                                    case 'absent':
                                                        echo '<span class="badge bg-danger">Absent</span>';
                                                        break;
                                                    case 'retard':
                                                        echo '<span class="badge bg-warning">En retard</span>';
                                                        break;
                                                }
                                                ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Non enregistré</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($signatureEleve): ?>
                                                <?php
                                                $statut = json_decode($signatureEleve['signature'], true);
                                                if (isset($statut['retard'])) {
                                                    echo $statut['retard'] . ' minutes';
                                                }
                                                ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$signatureEleve): ?>
                                                <button type="button" class="btn btn-sm btn-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalPresence<?php echo $eleve['id']; ?>">
                                                    Enregistrer
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <!-- Modal pour enregistrer la présence -->
                                    <div class="modal fade" id="modalPresence<?php echo $eleve['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Enregistrer la présence</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id_eleve" value="<?php echo $eleve['id']; ?>">

                                                        <div class="mb-3">
                                                            <label class="form-label">Statut</label>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="statut"
                                                                    value="present" id="present<?php echo $eleve['id']; ?>" checked>
                                                                <label class="form-check-label" for="present<?php echo $eleve['id']; ?>">
                                                                    Présent
                                                                </label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="statut"
                                                                    value="absent" id="absent<?php echo $eleve['id']; ?>">
                                                                <label class="form-check-label" for="absent<?php echo $eleve['id']; ?>">
                                                                    Absent
                                                                </label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="statut"
                                                                    value="retard" id="retard<?php echo $eleve['id']; ?>">
                                                                <label class="form-check-label" for="retard<?php echo $eleve['id']; ?>">
                                                                    En retard
                                                                </label>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3" id="retardInput<?php echo $eleve['id']; ?>" style="display: none;">
                                                            <label for="retard" class="form-label">Nombre de minutes de retard</label>
                                                            <input type="number" class="form-control" name="retard" min="1">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Afficher/masquer le champ de retard selon le statut sélectionné
        document.querySelectorAll('input[name="statut"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const eleveId = this.id.replace(/^(present|absent|retard)/, '');
                const retardInput = document.getElementById('retardInput' + eleveId);
                retardInput.style.display = this.value === 'retard' ? 'block' : 'none';
            });
        });
    </script>
</body>

</html>