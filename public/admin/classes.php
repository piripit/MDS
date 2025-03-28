<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';

session_start();

// Vérification de l'authentification et du rôle
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Traitement de la suppression
if (isset($_POST['delete']) && isset($_POST['id'])) {
    try {
        // Vérifier s'il y a des élèves dans la classe
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM affectations_eleves 
            WHERE id_classe = :id 
            AND annee_scolaire = :annee_scolaire
        ");
        $stmt->execute([
            ':id' => $_POST['id'],
            ':annee_scolaire' => date('Y') . '-' . (date('Y') + 1)
        ]);
        $count = $stmt->fetch()['count'];

        if ($count > 0) {
            $_SESSION['error'] = "Impossible de supprimer la classe car elle contient des élèves";
        } else {
            $stmt = $pdo->prepare("DELETE FROM classes WHERE id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            $_SESSION['success'] = "La classe a été supprimée avec succès";
        }
    } catch (Exception $e) {
        error_log("Erreur lors de la suppression de la classe: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de la suppression de la classe";
    }
}

// Traitement de l'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete'])) {
    $id = $_POST['id'] ?? null;
    $nom = $_POST['nom'] ?? '';
    $niveau = $_POST['niveau'] ?? '';
    $capacite = $_POST['capacite'] ?? 0;

    try {
        if ($id) {
            // Modification
            $stmt = $pdo->prepare("
                UPDATE classes 
                SET nom = :nom, niveau = :niveau, capacite = :capacite
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $id,
                ':nom' => $nom,
                ':niveau' => $niveau,
                ':capacite' => $capacite
            ]);
            $_SESSION['success'] = "La classe a été modifiée avec succès";
        } else {
            // Ajout
            $stmt = $pdo->prepare("
                INSERT INTO classes (nom, niveau, capacite)
                VALUES (:nom, :niveau, :capacite)
            ");
            $stmt->execute([
                ':nom' => $nom,
                ':niveau' => $niveau,
                ':capacite' => $capacite
            ]);
            $_SESSION['success'] = "La classe a été ajoutée avec succès";
        }

        header('Location: classes.php');
        exit();
    } catch (Exception $e) {
        error_log("Erreur lors de l'ajout/modification de la classe: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de l'ajout/modification de la classe";
    }
}

// Récupération des classes
try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(ae.id_eleve) as nb_eleves
        FROM classes c
        LEFT JOIN affectations_eleves ae ON c.id = ae.id_classe 
            AND ae.annee_scolaire = :annee_scolaire
        GROUP BY c.id, c.nom, c.niveau, c.capacite
        ORDER BY c.niveau, c.nom
    ");
    $stmt->execute([':annee_scolaire' => date('Y') . '-' . (date('Y') + 1)]);
    $classes = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des classes: " . $e->getMessage());
    $classes = [];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Classes - Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
                        <a class="nav-link" href="eleves.php">Élèves</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="enseignants.php">Enseignants</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="classes.php">Classes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="matieres.php">Matières</a>
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
        <h2>Gestion des Classes</h2>

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

        <!-- Bouton pour ajouter une classe -->
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modalClasse">
            Ajouter une classe
        </button>

        <!-- Liste des classes -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Niveau</th>
                                <th>Capacité</th>
                                <th>Élèves inscrits</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $classe): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($classe['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($classe['niveau']); ?></td>
                                    <td><?php echo $classe['capacite']; ?></td>
                                    <td><?php echo $classe['nb_eleves']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalClasse<?php echo $classe['id']; ?>">
                                            Modifier
                                        </button>
                                        <form method="POST" class="d-inline"
                                            onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette classe ?');">
                                            <input type="hidden" name="id" value="<?php echo $classe['id']; ?>">
                                            <button type="submit" name="delete" class="btn btn-sm btn-danger">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Modal de modification -->
                                <div class="modal fade" id="modalClasse<?php echo $classe['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Modifier la classe</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="id" value="<?php echo $classe['id']; ?>">

                                                    <div class="mb-3">
                                                        <label for="nom" class="form-label">Nom</label>
                                                        <input type="text" class="form-control" id="nom" name="nom"
                                                            value="<?php echo htmlspecialchars($classe['nom']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="niveau" class="form-label">Niveau</label>
                                                        <input type="text" class="form-control" id="niveau" name="niveau"
                                                            value="<?php echo htmlspecialchars($classe['niveau']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="capacite" class="form-label">Capacité</label>
                                                        <input type="number" class="form-control" id="capacite" name="capacite"
                                                            value="<?php echo $classe['capacite']; ?>" required min="1">
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
    </div>

    <!-- Modal d'ajout -->
    <div class="modal fade" id="modalClasse" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter une classe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>

                        <div class="mb-3">
                            <label for="niveau" class="form-label">Niveau</label>
                            <input type="text" class="form-control" id="niveau" name="niveau" required>
                        </div>

                        <div class="mb-3">
                            <label for="capacite" class="form-label">Capacité</label>
                            <input type="number" class="form-control" id="capacite" name="capacite" required min="1">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>