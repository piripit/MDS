<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';
require_once '../../classes/EmploiDuTemps.php';
require_once '../../classes/Signature.php';

session_start();

// Vérification de l'authentification et du rôle
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'eleve') {
    header('Location: ../login.php');
    exit();
}

$emploiDuTemps = new EmploiDuTemps($pdo);
$signature = new Signature($pdo);
$user = $_SESSION['user'];

// Récupération de la classe de l'élève
try {
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM affectations_eleves ae
        JOIN classes c ON ae.id_classe = c.id
        WHERE ae.id_eleve = :id_eleve
        AND ae.annee_scolaire = :annee_scolaire
        LIMIT 1
    ");
    $stmt->execute([
        ':id_eleve' => $user['id'],
        ':annee_scolaire' => date('Y') . '-' . (date('Y') + 1)
    ]);
    $classe = $stmt->fetch();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération de la classe: " . $e->getMessage());
    $classe = null;
}

// Récupération de l'emploi du temps de la classe
$emploiDuTempsClasse = $classe ? $emploiDuTemps->getEmploiDuTempsByClasse($classe['id']) : [];

// Récupération des signatures de l'élève
$signaturesEleve = $signature->getSignaturesByUser($user['id']);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Élève</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
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
                        <a class="nav-link active" href="dashboard.php">Tableau de bord</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="emploi_du_temps.php">Emploi du temps</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="presences.php">Mes présences</a>
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

        <?php if ($classe): ?>
            <div class="alert alert-info">
                Classe : <?php echo htmlspecialchars($classe['nom']); ?>
            </div>
        <?php endif; ?>

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
                                <th>Jour</th>
                                <th>Heure</th>
                                <th>Matière</th>
                                <th>Professeur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emploiDuTempsClasse as $cours): ?>
                                <tr>
                                    <td><?php echo $cours['jour']; ?></td>
                                    <td><?php echo date('H:i', strtotime($cours['heure'])); ?></td>
                                    <td><?php echo htmlspecialchars($cours['matiere_nom']); ?></td>
                                    <td><?php echo htmlspecialchars($cours['enseignant_prenom'] . ' ' . $cours['enseignant_nom']); ?></td>
                                    <td>
                                        <a href="../signature.php?matiere=<?php echo $cours['id_matiere']; ?>"
                                            class="btn btn-sm btn-primary">
                                            Signer
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Dernières signatures -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Dernières signatures</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Heure</th>
                                <th>Matière</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($signaturesEleve as $sig): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($sig['date'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($sig['heure'])); ?></td>
                                    <td><?php echo htmlspecialchars($sig['matiere_nom']); ?></td>
                                    <td>
                                        <?php
                                        $heurePrevue = strtotime($sig['heure']);
                                        $heureSignature = strtotime($sig['heure']);
                                        if ($heureSignature > $heurePrevue) {
                                            $retard = $heureSignature - $heurePrevue;
                                            echo '<span class="badge bg-warning">En retard (' . floor($retard / 60) . ' min)</span>';
                                        } else {
                                            echo '<span class="badge bg-success">À l\'heure</span>';
                                        }
                                        ?>
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