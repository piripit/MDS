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

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add':
                    $emploiDuTemps->ajouterCours(
                        $_POST['id_classe'],
                        $_POST['id_matiere'],
                        $_POST['id_enseignant'],
                        $_POST['jour'],
                        $_POST['heure_debut'],
                        $_POST['heure_fin']
                    );
                    $_SESSION['success'] = "Le cours a été ajouté avec succès.";
                    break;

                case 'edit':
                    $emploiDuTemps->modifierCours(
                        $_POST['id'],
                        $_POST['id_classe'],
                        $_POST['id_matiere'],
                        $_POST['id_enseignant'],
                        $_POST['jour'],
                        $_POST['heure_debut'],
                        $_POST['heure_fin']
                    );
                    $_SESSION['success'] = "Le cours a été modifié avec succès.";
                    break;

                case 'delete':
                    $emploiDuTemps->supprimerCours($_POST['id']);
                    $_SESSION['success'] = "Le cours a été supprimé avec succès.";
                    break;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur : " . $e->getMessage();
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Récupération des données
$classes = $emploiDuTemps->getClasses();
$matieres = $emploiDuTemps->getMatieres();
$enseignants = $emploiDuTemps->getEnseignants();
$cours = $emploiDuTemps->getCours();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de l'emploi du temps</title>
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

        <!-- Formulaire d'ajout/modification -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><?php echo isset($_GET['edit']) ? 'Modifier' : 'Ajouter'; ?> un cours</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo isset($_GET['edit']) ? 'edit' : 'add'; ?>">
                    <?php if (isset($_GET['edit'])): ?>
                        <input type="hidden" name="id" value="<?php echo $_GET['edit']; ?>">
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="id_classe" class="form-label">Classe</label>
                            <select class="form-select" id="id_classe" name="id_classe" required>
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

                        <div class="col-md-3 mb-3">
                            <label for="id_matiere" class="form-label">Matière</label>
                            <select class="form-select" id="id_matiere" name="id_matiere" required>
                                <option value="">Sélectionnez une matière</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="id_enseignant" class="form-label">Enseignant</label>
                            <select class="form-select" id="id_enseignant" name="id_enseignant" required>
                                <option value="">Sélectionnez un enseignant</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="jour" class="form-label">Jour</label>
                            <select class="form-select" id="jour" name="jour" required>
                                <option value="">Sélectionnez un jour</option>
                                <option value="1">Lundi</option>
                                <option value="2">Mardi</option>
                                <option value="3">Mercredi</option>
                                <option value="4">Jeudi</option>
                                <option value="5">Vendredi</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="heure_debut" class="form-label">Heure de début</label>
                            <select class="form-select" id="heure_debut" name="heure_debut" required>
                                <option value="">Sélectionnez l'heure</option>
                                <?php
                                for ($h = 8; $h <= 18; $h++) {
                                    for ($m = 0; $m < 60; $m += 15) {
                                        $heure = sprintf("%02d:%02d", $h, $m);
                                        echo "<option value='$heure'>$heure</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="heure_fin" class="form-label">Heure de fin</label>
                            <select class="form-select" id="heure_fin" name="heure_fin" required>
                                <option value="">Sélectionnez l'heure</option>
                                <?php
                                for ($h = 8; $h <= 18; $h++) {
                                    for ($m = 0; $m < 60; $m += 15) {
                                        $heure = sprintf("%02d:%02d", $h, $m);
                                        echo "<option value='$heure'>$heure</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <?php echo isset($_GET['edit']) ? 'Modifier' : 'Ajouter'; ?> le cours
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des cours -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Emploi du temps</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Jour</th>
                                <th>Heure</th>
                                <th>Classe</th>
                                <th>Matière</th>
                                <th>Enseignant</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cours as $c): ?>
                                <tr>
                                    <td><?php echo ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'][$c['jour']]; ?></td>
                                    <td><?php echo substr($c['heure_debut'], 0, 5) . ' - ' . substr($c['heure_fin'], 0, 5); ?></td>
                                    <td>
                                        <?php
                                        echo htmlspecialchars($c['classe_nom']);
                                        if (!empty($c['classe_option'])) {
                                            echo ' - ' . htmlspecialchars($c['classe_option']);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['matiere_nom']); ?></td>
                                    <td><?php echo htmlspecialchars($c['enseignant_nom'] . ' ' . $c['enseignant_prenom']); ?></td>
                                    <td>
                                        <a href="?edit=<?php echo $c['id']; ?>" class="btn btn-sm btn-primary">Modifier</a>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce cours ?');">
                                                Supprimer
                                            </button>
                                        </form>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validation des heures
            const heureDebut = document.getElementById('heure_debut');
            const heureFin = document.getElementById('heure_fin');

            heureDebut.addEventListener('change', function() {
                const debutValue = this.value;
                if (debutValue) {
                    // Désactiver les heures antérieures à l'heure de début
                    Array.from(heureFin.options).forEach(option => {
                        option.disabled = option.value && option.value <= debutValue;
                    });
                }
            });

            heureFin.addEventListener('change', function() {
                const finValue = this.value;
                const debutValue = heureDebut.value;
                if (finValue && debutValue && finValue <= debutValue) {
                    alert('L\'heure de fin doit être après l\'heure de début');
                    this.value = '';
                }
            });

            // Chargement des matières en fonction de la classe
            document.getElementById('id_classe').addEventListener('change', function() {
                const classeId = this.value;
                const matiereSelect = document.getElementById('id_matiere');
                const enseignantSelect = document.getElementById('id_enseignant');

                // Réinitialiser les sélecteurs
                matiereSelect.innerHTML = '<option value="">Sélectionnez une matière</option>';
                enseignantSelect.innerHTML = '<option value="">Sélectionnez un enseignant</option>';

                if (classeId) {
                    fetch(`get_matieres.php?classe_id=${classeId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Erreur lors du chargement des matières');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.error) {
                                throw new Error(data.error);
                            }
                            if (Array.isArray(data.matieres)) {
                                data.matieres.forEach(matiere => {
                                    const option = document.createElement('option');
                                    option.value = matiere.id;
                                    option.textContent = matiere.nom;
                                    matiereSelect.appendChild(option);
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            alert('Erreur lors du chargement des matières: ' + error.message);
                        });
                }
            });

            // Chargement des enseignants en fonction de la matière
            document.getElementById('id_matiere').addEventListener('change', function() {
                const matiereId = this.value;
                const enseignantSelect = document.getElementById('id_enseignant');

                enseignantSelect.innerHTML = '<option value="">Sélectionnez un enseignant</option>';

                if (matiereId) {
                    fetch(`get_enseignants.php?matiere_id=${matiereId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Erreur lors du chargement des enseignants');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.error) {
                                throw new Error(data.error);
                            }
                            if (Array.isArray(data)) {
                                data.forEach(enseignant => {
                                    const option = document.createElement('option');
                                    option.value = enseignant.id;
                                    option.textContent = enseignant.nom + ' ' + enseignant.prenom;
                                    enseignantSelect.appendChild(option);
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            alert('Erreur lors du chargement des enseignants: ' + error.message);
                        });
                }
            });
        });
    </script>
</body>

</html>