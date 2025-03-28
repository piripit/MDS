<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';
require_once '../../classes/EmploiDuTemps.php';

session_start();

// Vérification de l'authentification et du rôle
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$emploiDuTemps = new EmploiDuTemps($pdo);

// Récupération des classes
try {
    $stmt = $pdo->query("
        SELECT c.*,
               CASE 
                   WHEN c.niveau = 'BTS' THEN CONCAT(c.nom, ' - ', c.`option`)
                   ELSE c.nom 
               END as nom_complet
        FROM classes c
        ORDER BY c.niveau, c.nom, c.`option`
    ");
    $classes = $stmt->fetchAll();

    // Débogage
    error_log("Classes trouvées : " . print_r($classes, true));
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des classes: " . $e->getMessage());
    $classes = [];
}

// Récupération des matières
try {
    $stmt = $pdo->query("SELECT * FROM matieres ORDER BY nom");
    $matieres = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des matières: " . $e->getMessage());
    $matieres = [];
}

// Récupération des enseignants
try {
    $stmt = $pdo->query("SELECT * FROM utilisateurs WHERE role = 'enseignant' ORDER BY nom, prenom");
    $enseignants = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des enseignants: " . $e->getMessage());
    $enseignants = [];
}

// Traitement du formulaire d'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                $emploiDuTemps->ajouterCours(
                    $_POST['classe'],
                    $_POST['matiere'],
                    $_POST['enseignant'],
                    $_POST['jour'],
                    $_POST['heure_debut'],
                    $_POST['heure_fin']
                );
                $_SESSION['success'] = "Cours ajouté avec succès.";
            } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
                $emploiDuTemps->modifierCours(
                    $_POST['id'],
                    $_POST['classe'],
                    $_POST['matiere'],
                    $_POST['enseignant'],
                    $_POST['jour'],
                    $_POST['heure_debut'],
                    $_POST['heure_fin']
                );
                $_SESSION['success'] = "Cours modifié avec succès.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de l'opération: " . $e->getMessage();
    }
    header('Location: emploi_du_temps.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de l'emploi du temps - Administration</title>
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
                        <a class="nav-link active" href="emploi_du_temps.php">
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
        <h2>Gestion de l'emploi du temps</h2>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire d'ajout de cours -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Ajouter un cours</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="coursForm">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="classe" class="form-label">Classe</label>
                            <select class="form-select" id="classe" name="classe" required>
                                <option value="">Sélectionnez une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>">
                                        <?php echo htmlspecialchars($classe['nom_complet']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="matiere" class="form-label">Matière</label>
                            <select class="form-select" id="matiere" name="matiere" required>
                                <option value="">Sélectionnez une matière</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="enseignant" class="form-label">Enseignant</label>
                            <select class="form-select" id="enseignant" name="enseignant" required>
                                <option value="">Sélectionnez un enseignant</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="jour" class="form-label">Jour</label>
                            <select class="form-select" id="jour" name="jour" required>
                                <option value="1">Lundi</option>
                                <option value="2">Mardi</option>
                                <option value="3">Mercredi</option>
                                <option value="4">Jeudi</option>
                                <option value="5">Vendredi</option>
                                <option value="6">Samedi</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="heure_debut" class="form-label">Heure de début</label>
                            <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="heure_fin" class="form-label">Heure de fin</label>
                            <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                        </div>
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Ajouter le cours</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des cours -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Liste des cours</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Matière</th>
                                <th>Enseignant</th>
                                <th>Jour</th>
                                <th>Heures</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $cours = $emploiDuTemps->getCours();
                                foreach ($cours as $cours):
                                    $jour = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'][$cours['jour']];
                            ?>
                                    <tr>
                                        <td>
                                            <?php
                                            if ($cours['classe_niveau'] === 'BTS') {
                                                echo htmlspecialchars($cours['classe_nom'] . ' - ' . $cours['classe_option']);
                                            } else {
                                                echo htmlspecialchars($cours['classe_nom']);
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($cours['matiere_nom']); ?></td>
                                        <td><?php echo htmlspecialchars($cours['enseignant_nom']); ?></td>
                                        <td><?php echo $jour; ?></td>
                                        <td><?php echo $cours['heure_debut'] . ' - ' . $cours['heure_fin']; ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editModal<?php echo $cours['id']; ?>">
                                                Modifier
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteModal<?php echo $cours['id']; ?>">
                                                Supprimer
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Modal de modification -->
                                    <div class="modal fade" id="editModal<?php echo $cours['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Modifier le cours</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="edit">
                                                        <input type="hidden" name="id" value="<?php echo $cours['id']; ?>">

                                                        <div class="mb-3">
                                                            <label for="edit_classe<?php echo $cours['id']; ?>" class="form-label">Classe</label>
                                                            <select class="form-select" id="edit_classe<?php echo $cours['id']; ?>" name="classe" required>
                                                                <?php foreach ($classes as $classe): ?>
                                                                    <option value="<?php echo $classe['id']; ?>"
                                                                        <?php echo $classe['id'] == $cours['id_classe'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($classe['nom_complet']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="edit_matiere<?php echo $cours['id']; ?>" class="form-label">Matière</label>
                                                            <select class="form-select" id="edit_matiere<?php echo $cours['id']; ?>" name="matiere" required>
                                                                <?php foreach ($matieres as $matiere): ?>
                                                                    <option value="<?php echo $matiere['id']; ?>"
                                                                        <?php echo $matiere['id'] == $cours['id_matiere'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($matiere['nom']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="edit_enseignant<?php echo $cours['id']; ?>" class="form-label">Enseignant</label>
                                                            <select class="form-select" id="edit_enseignant<?php echo $cours['id']; ?>" name="enseignant" required>
                                                                <?php foreach ($enseignants as $enseignant): ?>
                                                                    <option value="<?php echo $enseignant['id']; ?>"
                                                                        <?php echo $enseignant['id'] == $cours['id_enseignant'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="edit_jour<?php echo $cours['id']; ?>" class="form-label">Jour</label>
                                                            <select class="form-select" id="edit_jour<?php echo $cours['id']; ?>" name="jour" required>
                                                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                                                    <option value="<?php echo $i; ?>"
                                                                        <?php echo $i == $cours['jour'] ? 'selected' : ''; ?>>
                                                                        <?php echo ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'][$i]; ?>
                                                                    </option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="edit_heure_debut<?php echo $cours['id']; ?>" class="form-label">Heure de début</label>
                                                            <input type="time" class="form-control" id="edit_heure_debut<?php echo $cours['id']; ?>"
                                                                name="heure_debut" value="<?php echo $cours['heure_debut']; ?>" required>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="edit_heure_fin<?php echo $cours['id']; ?>" class="form-label">Heure de fin</label>
                                                            <input type="time" class="form-control" id="edit_heure_fin<?php echo $cours['id']; ?>"
                                                                name="heure_fin" value="<?php echo $cours['heure_fin']; ?>" required>
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

                                    <!-- Modal de suppression -->
                                    <div class="modal fade" id="deleteModal<?php echo $cours['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Confirmer la suppression</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Êtes-vous sûr de vouloir supprimer ce cours ?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $cours['id']; ?>">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                        <button type="submit" class="btn btn-danger">Supprimer</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                            <?php endforeach;
                            } catch (Exception $e) {
                                error_log("Erreur lors de la récupération des cours: " . $e->getMessage());
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const classeSelect = document.getElementById('classe');
            const matiereSelect = document.getElementById('matiere');
            const enseignantSelect = document.getElementById('enseignant');

            // Fonction pour charger toutes les matières
            function loadMatieres() {
                fetch('get_matieres.php')
                    .then(response => response.json())
                    .then(data => {
                        matiereSelect.innerHTML = '<option value="">Sélectionnez une matière</option>';
                        data.forEach(matiere => {
                            const option = document.createElement('option');
                            option.value = matiere.id;
                            option.textContent = matiere.nom;
                            matiereSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Erreur lors du chargement des matières:', error);
                        matiereSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                    });
            }

            // Fonction pour charger tous les enseignants
            function loadEnseignants() {
                fetch('get_enseignants.php')
                    .then(response => response.json())
                    .then(data => {
                        enseignantSelect.innerHTML = '<option value="">Sélectionnez un enseignant</option>';
                        data.forEach(enseignant => {
                            const option = document.createElement('option');
                            option.value = enseignant.id;
                            option.textContent = enseignant.prenom + ' ' + enseignant.nom;
                            enseignantSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Erreur lors du chargement des enseignants:', error);
                        enseignantSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                    });
            }

            // Charger les matières et les enseignants au démarrage
            loadMatieres();
            loadEnseignants();
        });
    </script>
</body>

</html>