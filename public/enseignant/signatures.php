<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Signature.php';
require_once '../../classes/EmploiDuTemps.php';

session_start();

// Vérification de l'authentification et du rôle
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'enseignant') {
    header('Location: ../login.php');
    exit();
}

$signature = new Signature($pdo);
$emploiDuTemps = new EmploiDuTemps($pdo);
$user = $_SESSION['user'];

// Récupération des matières de l'enseignant
$matieres = $emploiDuTemps->getMatieresByEnseignant($user['id']);

// Traitement de la demande de signature
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['demander_signature'])) {
    $id_matiere = $_POST['id_matiere'];
    $id_classe = $_POST['id_classe'];
    $date = $_POST['date'];

    // TODO: Envoyer une notification aux élèves
    $_SESSION['success'] = "Demande de signature envoyée aux élèves";
    header('Location: signatures.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Signatures - Enseignant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h2>Gestion des Signatures</h2>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Demande de signature -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Demander une signature</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="matiere" class="form-label">Matière</label>
                                <select class="form-select" name="id_matiere" required>
                                    <option value="">Sélectionnez une matière</option>
                                    <?php foreach ($matieres as $matiere): ?>
                                        <option value="<?php echo $matiere['id']; ?>">
                                            <?php echo htmlspecialchars($matiere['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="classe" class="form-label">Classe</label>
                                <select class="form-select" name="id_classe" required>
                                    <option value="">Sélectionnez une classe</option>
                                    <?php foreach ($matieres as $matiere): ?>
                                        <option value="<?php echo $matiere['id_classe']; ?>">
                                            <?php echo htmlspecialchars($matiere['classe_nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" class="form-control" name="date" required>
                            </div>

                            <button type="submit" name="demander_signature" class="btn btn-primary">
                                Demander la signature
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Liste des signatures -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Signatures reçues</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Matière</th>
                                        <th>Classe</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $signatures = $signature->getSignaturesByMatiere(
                                        $_GET['matiere'] ?? null,
                                        date('Y-m-d')
                                    );
                                    foreach ($signatures as $sig): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($sig['date'])); ?></td>
                                            <td><?php echo htmlspecialchars($sig['matiere_nom']); ?></td>
                                            <td><?php echo htmlspecialchars($sig['classe_nom']); ?></td>
                                            <td>
                                                <a href="voir_signatures.php?id=<?php echo $sig['id']; ?>"
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>