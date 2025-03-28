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
        // Vérifier s'il y a des cours associés à la matière
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM emplois_du_temps 
            WHERE id_matiere = :id
        ");
        $stmt->execute([':id' => $_POST['id']]);
        $count = $stmt->fetch()['count'];

        if ($count > 0) {
            $_SESSION['error'] = "Impossible de supprimer la matière car elle est utilisée dans l'emploi du temps";
        } else {
            $stmt = $pdo->prepare("DELETE FROM matieres WHERE id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            $_SESSION['success'] = "La matière a été supprimée avec succès";
        }
    } catch (Exception $e) {
        error_log("Erreur lors de la suppression de la matière: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de la suppression de la matière";
    }
}

// Traitement de l'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete'])) {
    $id = $_POST['id'] ?? null;
    $nom = $_POST['nom'] ?? '';
    $description = $_POST['description'] ?? '';
    $classe = $_POST['classe'] ?? null;
    $enseignant = $_POST['enseignant'] ?? null;

    try {
        if ($id) {
            // Modification
            $stmt = $pdo->prepare("
                UPDATE matieres 
                SET nom = :nom, description = :description, id_classe = :id_classe
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $id,
                ':nom' => $nom,
                ':description' => $description,
                ':id_classe' => $classe
            ]);

            // Mise à jour de l'enseignant
            $stmt = $pdo->prepare("DELETE FROM matieres_enseignants WHERE id_matiere = :id");
            $stmt->execute([':id' => $id]);

            if ($enseignant) {
                $stmt = $pdo->prepare("
                    INSERT INTO matieres_enseignants (id_matiere, id_enseignant)
                    VALUES (:id_matiere, :id_enseignant)
                ");
                $stmt->execute([
                    ':id_matiere' => $id,
                    ':id_enseignant' => $enseignant
                ]);
            }

            $_SESSION['success'] = "La matière a été modifiée avec succès";
        } else {
            // Ajout
            $stmt = $pdo->prepare("
                INSERT INTO matieres (nom, description, id_classe)
                VALUES (:nom, :description, :id_classe)
            ");
            $stmt->execute([
                ':nom' => $nom,
                ':description' => $description,
                ':id_classe' => $classe
            ]);

            $id_matiere = $pdo->lastInsertId();

            if ($enseignant) {
                $stmt = $pdo->prepare("
                    INSERT INTO matieres_enseignants (id_matiere, id_enseignant)
                    VALUES (:id_matiere, :id_enseignant)
                ");
                $stmt->execute([
                    ':id_matiere' => $id_matiere,
                    ':id_enseignant' => $enseignant
                ]);
            }

            $_SESSION['success'] = "La matière a été ajoutée avec succès";
        }

        header('Location: matieres.php');
        exit();
    } catch (Exception $e) {
        error_log("Erreur lors de l'ajout/modification de la matière: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de l'ajout/modification de la matière";
    }
}

// Récupération des matières
try {
    $stmt = $pdo->prepare("
        SELECT m.*, c.nom as classe_nom, 
               GROUP_CONCAT(CONCAT(u.prenom, ' ', u.nom)) as enseignants_noms
        FROM matieres m
        LEFT JOIN classes c ON m.id_classe = c.id
        LEFT JOIN matieres_enseignants me ON m.id = me.id_matiere
        LEFT JOIN utilisateurs u ON me.id_enseignant = u.id
        GROUP BY m.id, m.nom, m.description, m.id_classe, c.nom
        ORDER BY m.nom
    ");
    $stmt->execute();
    $matieres = $stmt->fetchAll();

    // Récupération des classes pour le formulaire
    $stmt = $pdo->prepare("SELECT * FROM classes ORDER BY niveau, nom");
    $stmt->execute();
    $classes = $stmt->fetchAll();

    // Récupération des enseignants pour le formulaire
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE role = 'enseignant' ORDER BY nom, prenom");
    $stmt->execute();
    $enseignants = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des données: " . $e->getMessage());
    $matieres = [];
    $classes = [];
    $enseignants = [];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Matières - Administration</title>
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
                        <a class="nav-link" href="classes.php">Classes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="matieres.php">Matières</a>
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
        <h2>Gestion des Matières</h2>

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

        <!-- Bouton pour ajouter une matière -->
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modalMatiere">
            Ajouter une matière
        </button>

        <!-- Liste des matières -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Classe</th>
                                <th>Enseignant</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matieres as $matiere): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($matiere['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($matiere['description']); ?></td>
                                    <td><?php echo htmlspecialchars($matiere['classe_nom'] ?? 'Non assignée'); ?></td>
                                    <td>
                                        <?php
                                        if ($matiere['enseignants_noms']) {
                                            echo htmlspecialchars($matiere['enseignants_noms']);
                                        } else {
                                            echo 'Non assigné';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalMatiere<?php echo $matiere['id']; ?>">
                                            Modifier
                                        </button>
                                        <form method="POST" class="d-inline"
                                            onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette matière ?');">
                                            <input type="hidden" name="id" value="<?php echo $matiere['id']; ?>">
                                            <button type="submit" name="delete" class="btn btn-sm btn-danger">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Modal de modification -->
                                <div class="modal fade" id="modalMatiere<?php echo $matiere['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Modifier la matière</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="id" value="<?php echo $matiere['id']; ?>">

                                                    <div class="mb-3">
                                                        <label for="nom" class="form-label">Nom</label>
                                                        <input type="text" class="form-control" id="nom" name="nom"
                                                            value="<?php echo htmlspecialchars($matiere['nom']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="description" class="form-label">Description</label>
                                                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($matiere['description']); ?></textarea>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="classe" class="form-label">Classe</label>
                                                        <select class="form-select" id="classe" name="classe" required>
                                                            <option value="">Sélectionnez une classe</option>
                                                            <?php foreach ($classes as $classe): ?>
                                                                <option value="<?php echo $classe['id']; ?>"
                                                                    <?php echo $matiere['id_classe'] == $classe['id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($classe['nom']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="enseignant" class="form-label">Enseignant</label>
                                                        <select class="form-select" id="enseignant" name="enseignant">
                                                            <option value="">Sélectionnez un enseignant</option>
                                                            <?php
                                                            $enseignants_matiere = [];
                                                            $stmt = $pdo->prepare("
                                                                SELECT id_enseignant 
                                                                FROM matieres_enseignants 
                                                                WHERE id_matiere = :id
                                                            ");
                                                            $stmt->execute([':id' => $matiere['id']]);
                                                            while ($row = $stmt->fetch()) {
                                                                $enseignants_matiere[] = $row['id_enseignant'];
                                                            }
                                                            foreach ($enseignants as $enseignant):
                                                            ?>
                                                                <option value="<?php echo $enseignant['id']; ?>"
                                                                    <?php echo in_array($enseignant['id'], $enseignants_matiere) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
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
    <div class="modal fade" id="modalMatiere" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter une matière</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="classe" class="form-label">Classe</label>
                            <select class="form-select" id="classe" name="classe" required>
                                <option value="">Sélectionnez une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>">
                                        <?php echo htmlspecialchars($classe['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="enseignant" class="form-label">Enseignant</label>
                            <select class="form-select" id="enseignant" name="enseignant">
                                <option value="">Sélectionnez un enseignant</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?php echo $enseignant['id']; ?>">
                                        <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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