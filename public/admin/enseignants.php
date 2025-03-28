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
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = :id AND role = 'enseignant'");
        $stmt->execute([':id' => $_POST['id']]);
        $_SESSION['success'] = "L'enseignant a été supprimé avec succès";
    } catch (Exception $e) {
        error_log("Erreur lors de la suppression de l'enseignant: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de la suppression de l'enseignant";
    }
}

// Traitement de l'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete'])) {
    $id = $_POST['id'] ?? null;
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $matieres = $_POST['matieres'] ?? [];

    try {
        if ($id) {
            // Modification
            $stmt = $pdo->prepare("
                UPDATE utilisateurs 
                SET nom = :nom, prenom = :prenom, email = :email
                WHERE id = :id AND role = 'enseignant'
            ");
            $stmt->execute([
                ':id' => $id,
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':email' => $email
            ]);

            // Mise à jour des matières enseignées
            $stmt = $pdo->prepare("DELETE FROM matieres_enseignants WHERE id_enseignant = :id");
            $stmt->execute([':id' => $id]);

            foreach ($matieres as $matiere_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO matieres_enseignants (id_enseignant, id_matiere)
                    VALUES (:id_enseignant, :id_matiere)
                ");
                $stmt->execute([
                    ':id_enseignant' => $id,
                    ':id_matiere' => $matiere_id
                ]);
            }

            $_SESSION['success'] = "L'enseignant a été modifié avec succès";
        } else {
            // Ajout
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs (nom, prenom, email, password, role)
                VALUES (:nom, :prenom, :email, :password, 'enseignant')
            ");
            $stmt->execute([
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':email' => $email,
                ':password' => $password_hash
            ]);

            $id_enseignant = $pdo->lastInsertId();

            foreach ($matieres as $matiere_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO matieres_enseignants (id_enseignant, id_matiere)
                    VALUES (:id_enseignant, :id_matiere)
                ");
                $stmt->execute([
                    ':id_enseignant' => $id_enseignant,
                    ':id_matiere' => $matiere_id
                ]);
            }

            // Envoi de l'email avec les identifiants
            $mailer->sendCredentials($email, $prenom . ' ' . $nom, $email, $password);
            $_SESSION['success'] = "L'enseignant a été ajouté avec succès et les identifiants ont été envoyés par email";
        }

        header('Location: enseignants.php');
        exit();
    } catch (Exception $e) {
        error_log("Erreur lors de l'ajout/modification de l'enseignant: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de l'ajout/modification de l'enseignant";
    }
}

// Récupération des enseignants
try {
    $stmt = $pdo->prepare("
        SELECT u.*, GROUP_CONCAT(m.nom) as matieres_noms
        FROM utilisateurs u
        LEFT JOIN matieres_enseignants me ON u.id = me.id_enseignant
        LEFT JOIN matieres m ON me.id_matiere = m.id
        WHERE u.role = 'enseignant'
        GROUP BY u.id, u.nom, u.prenom, u.email, u.role
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute();
    $enseignants = $stmt->fetchAll();

    // Récupération des matières pour le formulaire
    $stmt = $pdo->prepare("SELECT * FROM matieres ORDER BY nom");
    $stmt->execute();
    $matieres = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des données: " . $e->getMessage());
    $enseignants = [];
    $matieres = [];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Enseignants - Administration</title>
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
                        <a class="nav-link active" href="enseignants.php">Enseignants</a>
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
        <h2>Gestion des Enseignants</h2>

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

        <!-- Bouton pour ajouter un enseignant -->
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modalEnseignant">
            Ajouter un enseignant
        </button>

        <!-- Liste des enseignants -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Email</th>
                                <th>Matières</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enseignants as $enseignant): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($enseignant['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($enseignant['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($enseignant['email']); ?></td>
                                    <td><?php echo htmlspecialchars($enseignant['matieres_noms'] ?? 'Aucune matière'); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEnseignant<?php echo $enseignant['id']; ?>">
                                            Modifier
                                        </button>
                                        <form method="POST" class="d-inline"
                                            onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet enseignant ?');">
                                            <input type="hidden" name="id" value="<?php echo $enseignant['id']; ?>">
                                            <button type="submit" name="delete" class="btn btn-sm btn-danger">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Modal de modification -->
                                <div class="modal fade" id="modalEnseignant<?php echo $enseignant['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Modifier l'enseignant</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="id" value="<?php echo $enseignant['id']; ?>">

                                                    <div class="mb-3">
                                                        <label for="nom" class="form-label">Nom</label>
                                                        <input type="text" class="form-control" id="nom" name="nom"
                                                            value="<?php echo htmlspecialchars($enseignant['nom']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="prenom" class="form-label">Prénom</label>
                                                        <input type="text" class="form-control" id="prenom" name="prenom"
                                                            value="<?php echo htmlspecialchars($enseignant['prenom']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="email" class="form-label">Email</label>
                                                        <input type="email" class="form-control" id="email" name="email"
                                                            value="<?php echo htmlspecialchars($enseignant['email']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Matières enseignées</label>
                                                        <?php
                                                        $matieres_enseignant = [];
                                                        $stmt = $pdo->prepare("
                                                            SELECT id_matiere 
                                                            FROM matieres_enseignants 
                                                            WHERE id_enseignant = :id
                                                        ");
                                                        $stmt->execute([':id' => $enseignant['id']]);
                                                        while ($row = $stmt->fetch()) {
                                                            $matieres_enseignant[] = $row['id_matiere'];
                                                        }
                                                        ?>
                                                        <?php foreach ($matieres as $matiere): ?>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox"
                                                                    name="matieres[]" value="<?php echo $matiere['id']; ?>"
                                                                    id="matiere<?php echo $matiere['id']; ?>"
                                                                    <?php echo in_array($matiere['id'], $matieres_enseignant) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="matiere<?php echo $matiere['id']; ?>">
                                                                    <?php echo htmlspecialchars($matiere['nom']); ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
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
    <div class="modal fade" id="modalEnseignant" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un enseignant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
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
                            <label class="form-label">Matières enseignées</label>
                            <?php foreach ($matieres as $matiere): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                        name="matieres[]" value="<?php echo $matiere['id']; ?>"
                                        id="matiere<?php echo $matiere['id']; ?>">
                                    <label class="form-check-label" for="matiere<?php echo $matiere['id']; ?>">
                                        <?php echo htmlspecialchars($matiere['nom']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
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