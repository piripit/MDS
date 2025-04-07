<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Mailer.php';

session_start();

// Vérification de l'authentification et du rôle
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$mailer = new Mailer();

// Traitement de la suppression
if (isset($_POST['delete']) && isset($_POST['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = :id AND role = 'eleve'");
        $stmt->execute([':id' => $_POST['id']]);
        $_SESSION['success'] = "L'élève a été supprimé avec succès";
    } catch (Exception $e) {
        error_log("Erreur lors de la suppression de l'élève: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de la suppression de l'élève";
    }
}

// Traitement de l'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete'])) {
    $id = $_POST['id'] ?? null;
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $classe = $_POST['classe'] ?? null;

    try {
        if ($id) {
            // Modification
            $stmt = $pdo->prepare("
                UPDATE utilisateurs 
                SET nom = :nom, prenom = :prenom, email = :email
                WHERE id = :id AND role = 'eleve'
            ");
            $stmt->execute([
                ':id' => $id,
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':email' => $email
            ]);

            // Mise à jour de la classe
            $stmt = $pdo->prepare("
                UPDATE affectations_eleves 
                SET id_classe = :id_classe
                WHERE id_eleve = :id_eleve
                AND annee_scolaire = :annee_scolaire
            ");
            $stmt->execute([
                ':id_eleve' => $id,
                ':id_classe' => $classe,
                ':annee_scolaire' => date('Y') . '-' . (date('Y') + 1)
            ]);

            $_SESSION['success'] = "L'élève a été modifié avec succès";
        } else {
            // Ajout
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs (nom, prenom, email, password, role)
                VALUES (:nom, :prenom, :email, :password, 'eleve')
            ");
            $stmt->execute([
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':email' => $email,
                ':password' => $password_hash
            ]);

            $id_eleve = $pdo->lastInsertId();

            // Affectation à la classe
            $stmt = $pdo->prepare("
                INSERT INTO affectations_eleves (id_eleve, id_classe, annee_scolaire)
                VALUES (:id_eleve, :id_classe, :annee_scolaire)
            ");
            $stmt->execute([
                ':id_eleve' => $id_eleve,
                ':id_classe' => $classe,
                ':annee_scolaire' => date('Y') . '-' . (date('Y') + 1)
            ]);

            // Envoi de l'email avec les identifiants
            $mailer->sendCredentials($email, $prenom . ' ' . $nom, $email, $password);
            $_SESSION['success'] = "L'élève a été ajouté avec succès et les identifiants ont été envoyés par email";
        }

        header('Location: eleves.php');
        exit();
    } catch (Exception $e) {
        error_log("Erreur lors de l'ajout/modification de l'élève: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de l'ajout/modification de l'élève";
    }
}

// Récupération des élèves
try {
    $stmt = $pdo->prepare("
        SELECT u.*, c.nom as classe_nom
        FROM utilisateurs u
        LEFT JOIN affectations_eleves ae ON u.id = ae.id_eleve
        LEFT JOIN classes c ON ae.id_classe = c.id
        WHERE u.role = 'eleve'
        AND (ae.annee_scolaire = :annee_scolaire OR ae.annee_scolaire IS NULL)
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute([':annee_scolaire' => date('Y') . '-' . (date('Y') + 1)]);
    $eleves = $stmt->fetchAll();

    // Récupération des classes pour le formulaire
    $stmt = $pdo->prepare("
        SELECT id, nom, niveau
        FROM classes 
        ORDER BY nom, niveau
    ");
    $stmt->execute();
    $classes = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des données: " . $e->getMessage());
    $eleves = [];
    $classes = [];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Élèves - Administration</title>
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
                        <a class="nav-link active" href="eleves.php">Élèves</a>
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
        <h2>Gestion des Élèves</h2>

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

        <!-- Bouton pour ajouter un élève -->
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modalEleve">
            Ajouter un élève
        </button>

        <!-- Liste des élèves -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Email</th>
                                <th>Classe</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eleves as $eleve): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($eleve['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($eleve['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($eleve['email']); ?></td>
                                    <td><?php echo htmlspecialchars($eleve['classe_nom'] ?? 'Non assigné'); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEleve<?php echo $eleve['id']; ?>">
                                            Modifier
                                        </button>
                                        <form method="POST" class="d-inline"
                                            onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet élève ?');">
                                            <input type="hidden" name="id" value="<?php echo $eleve['id']; ?>">
                                            <button type="submit" name="delete" class="btn btn-sm btn-danger">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Modal de modification -->
                                <div class="modal fade" id="modalEleve<?php echo $eleve['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Modifier l'élève</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="id" value="<?php echo $eleve['id']; ?>">

                                                    <div class="mb-3">
                                                        <label for="nom" class="form-label">Nom</label>
                                                        <input type="text" class="form-control" id="nom" name="nom"
                                                            value="<?php echo htmlspecialchars($eleve['nom']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="prenom" class="form-label">Prénom</label>
                                                        <input type="text" class="form-control" id="prenom" name="prenom"
                                                            value="<?php echo htmlspecialchars($eleve['prenom']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="email" class="form-label">Email</label>
                                                        <input type="email" class="form-control" id="email" name="email"
                                                            value="<?php echo htmlspecialchars($eleve['email']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="classe" class="form-label">Classe</label>
                                                        <select class="form-select" id="classe" name="classe" required>
                                                            <option value="">Sélectionnez une classe</option>
                                                            <?php foreach ($classes as $classe): ?>
                                                                <option value="<?php echo $classe['id']; ?>"
                                                                    <?php echo $eleve['classe_nom'] === $classe['nom'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($classe['nom']); ?>
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

    <!-- Modal pour ajouter/modifier un élève -->
    <div class="modal fade" id="modalEleve" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un élève</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="id" id="eleve_id">
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        <div class="mb-3">
                            <label for="prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="classe" class="form-label">Classe</label>
                            <select class="form-control" id="classe" name="classe" required>
                                <option value="">Sélectionnez une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>">
                                        <?php
                                        echo htmlspecialchars($classe['niveau'] . ' ' . $classe['nom']);
                                        if (!empty($classe['option'])) {
                                            echo ' - ' . htmlspecialchars($classe['option']);
                                        }
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>