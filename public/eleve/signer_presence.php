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

// Récupération des cours de l'élève pour aujourd'hui
$jour_actuel = date('N'); // 1 pour lundi, 7 pour dimanche
$cours_aujourdhui = [];

try {
    // Récupérer les cours de l'élève
    $stmt = $pdo->prepare("
        SELECT 
            edt.*,
            m.nom as matiere_nom,
            u.prenom as enseignant_prenom,
            u.nom as enseignant_nom,
            c.nom as classe_nom,
            c.niveau as classe_niveau
        FROM emploi_du_temps edt
        INNER JOIN matieres m ON edt.id_matiere = m.id
        INNER JOIN utilisateurs u ON edt.id_enseignant = u.id
        INNER JOIN classes c ON m.id_classe = c.id
        INNER JOIN affectations_eleves ae ON c.id = ae.id_classe
        WHERE ae.id_eleve = ? AND edt.jour = ?
        ORDER BY edt.heure_debut
    ");
    $stmt->execute([$user['id'], $jour_actuel]);
    $cours_aujourdhui = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des cours: " . $e->getMessage());
}

// Récupération des sessions actives pour les cours de l'élève
$sessions_actives = [];
$signatures_jour = [];

foreach ($cours_aujourdhui as $cours) {
    // Vérifier si une session est active
    $session = $signature->sessionActive($cours['id_matiere'], $cours['id_enseignant']);
    if ($session) {
        $sessions_actives[$cours['id_matiere']] = $session;
    }

    // Récupérer les signatures déjà effectuées aujourd'hui
    $stmt = $pdo->prepare("
        SELECT * FROM signatures 
        WHERE id_eleve = ? AND id_matiere = ? AND date_signature = ?
    ");
    $stmt->execute([$user['id'], $cours['id_matiere'], date('Y-m-d')]);
    $sig = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sig) {
        $signatures_jour[$cours['id_matiere']] = $sig;
    }
}

// Traitement de la signature
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signer_presence'])) {
    $id_matiere = $_POST['id_matiere'];
    $commentaire = $_POST['commentaire'] ?? null;

    try {
        $signature->signerPresence($user['id'], $id_matiere, $commentaire);
        $_SESSION['success'] = "Votre présence a été enregistrée avec succès !";
        header("Location: signer_presence.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$joursNoms = [
    1 => 'Lundi',
    2 => 'Mardi',
    3 => 'Mercredi',
    4 => 'Jeudi',
    5 => 'Vendredi',
    6 => 'Samedi',
    7 => 'Dimanche'
];
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signer ma présence - Élève</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .cours-card {
            transition: all 0.3s ease;
            border-left: 4px solid #6c757d;
        }

        .cours-actif {
            border-left: 4px solid #28a745;
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
        }

        .cours-signe {
            border-left: 4px solid #007bff;
            background-color: #f8f9fa;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
        }

        .countdown {
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-info">
        <div class="container">
            <a class="navbar-brand" href="#">Espace Élève</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Tableau de bord</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="signer_presence.php">
                            <i class="bi bi-pen"></i> Signer présence
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="emploi_du_temps.php">Emploi du temps</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="presences.php">Mes présences</a>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-pen"></i> Signer ma présence</h2>
            <div class="badge bg-info fs-6">
                <?php echo $joursNoms[$jour_actuel] . ' ' . date('d/m/Y'); ?>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo $_SESSION['success'];
                                                    unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $_SESSION['error'];
                                                            unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Sessions actives disponibles -->
        <?php
        $sessions_disponibles = array_filter($cours_aujourdhui, function ($cours) use ($sessions_actives, $signatures_jour) {
            return isset($sessions_actives[$cours['id_matiere']]) && !isset($signatures_jour[$cours['id_matiere']]);
        });
        ?>

        <?php if (!empty($sessions_disponibles)): ?>
            <div class="alert alert-success">
                <h5><i class="bi bi-broadcast-pin"></i> Sessions de signature actives !</h5>
                <p class="mb-0">Vous pouvez maintenant signer votre présence pour vos cours ci-dessous.</p>
            </div>
        <?php endif; ?>

        <?php if (empty($cours_aujourdhui)): ?>
            <div class="alert alert-info">
                <h4><i class="bi bi-calendar-x"></i> Pas de cours aujourd'hui</h4>
                <p class="mb-0">Vous n'avez pas de cours programmés pour aujourd'hui.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($cours_aujourdhui as $cours): ?>
                    <?php
                    $session_active = isset($sessions_actives[$cours['id_matiere']]);
                    $deja_signe = isset($signatures_jour[$cours['id_matiere']]);
                    $peut_signer = $session_active && !$deja_signe;

                    $card_class = 'cours-card';
                    if ($peut_signer) {
                        $card_class .= ' cours-actif pulse';
                    } elseif ($deja_signe) {
                        $card_class .= ' cours-signe';
                    }
                    ?>

                    <div class="col-md-6 mb-4">
                        <div class="card <?php echo $card_class; ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <?php echo htmlspecialchars($cours['matiere_nom']); ?>
                                </h5>
                                <span class="badge bg-secondary">
                                    <?php echo substr($cours['heure_debut'], 0, 5); ?> - <?php echo substr($cours['heure_fin'], 0, 5); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    <i class="bi bi-person"></i>
                                    <strong>Enseignant :</strong>
                                    <?php echo htmlspecialchars($cours['enseignant_prenom'] . ' ' . $cours['enseignant_nom']); ?>
                                    <br>
                                    <i class="bi bi-mortarboard"></i>
                                    <strong>Classe :</strong>
                                    <?php echo htmlspecialchars($cours['classe_niveau'] . ' ' . $cours['classe_nom']); ?>
                                </p>

                                <?php if ($peut_signer): ?>
                                    <div class="alert alert-success mb-3">
                                        <i class="bi bi-broadcast-pin"></i>
                                        <strong>Session ouverte !</strong>
                                        <br>
                                        <small>
                                            Temps restant :
                                            <span class="countdown"
                                                data-debut="<?php echo $sessions_actives[$cours['id_matiere']]['heure_debut']; ?>"
                                                data-duree="<?php echo $sessions_actives[$cours['id_matiere']]['duree_minutes']; ?>">
                                            </span>
                                        </small>
                                    </div>

                                    <form method="POST" class="signature-form">
                                        <input type="hidden" name="id_matiere" value="<?php echo $cours['id_matiere']; ?>">
                                        <div class="mb-3">
                                            <label for="commentaire<?php echo $cours['id_matiere']; ?>" class="form-label">
                                                Commentaire (optionnel)
                                            </label>
                                            <textarea class="form-control" name="commentaire"
                                                id="commentaire<?php echo $cours['id_matiere']; ?>"
                                                rows="2" placeholder="Ajouter un commentaire..."></textarea>
                                        </div>
                                        <button type="submit" name="signer_presence" class="btn btn-success btn-lg w-100">
                                            <i class="bi bi-pen"></i> Signer ma présence
                                        </button>
                                    </form>

                                <?php elseif ($deja_signe): ?>
                                    <?php $sig = $signatures_jour[$cours['id_matiere']]; ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="bi bi-check-circle"></i>
                                        <strong>Déjà signé !</strong>
                                        <br>
                                        <small>
                                            Statut :
                                            <?php
                                            $statut_text = [
                                                'present' => '<span class="badge bg-success">Présent</span>',
                                                'retard' => '<span class="badge bg-warning">Retard (' . $sig['retard_minutes'] . ' min)</span>',
                                                'absent' => '<span class="badge bg-danger">Absent</span>'
                                            ];
                                            echo $statut_text[$sig['statut']];
                                            ?>
                                            <br>
                                            Heure : <?php echo substr($sig['heure_signature'], 0, 5); ?>
                                            <?php if ($sig['commentaire']): ?>
                                                <br>Commentaire : <?php echo htmlspecialchars($sig['commentaire']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <button class="btn btn-outline-success btn-lg w-100" disabled>
                                        <i class="bi bi-check-circle"></i> Présence enregistrée
                                    </button>

                                <?php else: ?>
                                    <div class="alert alert-warning mb-3">
                                        <i class="bi bi-hourglass-split"></i>
                                        <strong>Session fermée</strong>
                                        <br>
                                        <small>L'enseignant n'a pas encore ouvert la session de signature pour ce cours.</small>
                                    </div>
                                    <button class="btn btn-outline-secondary btn-lg w-100" disabled>
                                        <i class="bi bi-lock"></i> Signature non disponible
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Récapitulatif du jour -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-bar-chart"></i> Récapitulatif du jour
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="fs-4 text-primary"><?php echo count($cours_aujourdhui); ?></div>
                            <div>Total cours</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-4 text-success"><?php echo count($signatures_jour); ?></div>
                            <div>Présences signées</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-4 text-warning"><?php echo count($sessions_disponibles); ?></div>
                            <div>Sessions ouvertes</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-4 text-info">
                                <?php echo count($cours_aujourdhui) - count($signatures_jour); ?>
                            </div>
                            <div>En attente</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonction pour calculer le temps restant
        function updateCountdowns() {
            document.querySelectorAll('.countdown').forEach(function(element) {
                const heureDebut = element.dataset.debut;
                const dureeMinutes = parseInt(element.dataset.duree);

                const maintenant = new Date();
                const heureActuelle = maintenant.getHours() + ':' +
                    maintenant.getMinutes().toString().padStart(2, '0') + ':' +
                    maintenant.getSeconds().toString().padStart(2, '0');

                const debut = new Date('1970-01-01T' + heureDebut);
                const fin = new Date(debut.getTime() + (dureeMinutes * 60000));
                const current = new Date('1970-01-01T' + heureActuelle);

                const tempsRestant = fin - current;

                if (tempsRestant > 0) {
                    const minutes = Math.floor(tempsRestant / 60000);
                    const secondes = Math.floor((tempsRestant % 60000) / 1000);
                    element.textContent = minutes + 'm ' + secondes.toString().padStart(2, '0') + 's';
                } else {
                    element.textContent = 'Expiré';
                    element.style.color = '#dc3545';
                    // Désactiver le formulaire
                    const form = element.closest('.card').querySelector('.signature-form');
                    if (form) {
                        const button = form.querySelector('button[type="submit"]');
                        button.disabled = true;
                        button.innerHTML = '<i class="bi bi-clock"></i> Session expirée';
                        button.classList.remove('btn-success');
                        button.classList.add('btn-danger');
                    }
                }
            });
        }

        // Mettre à jour toutes les secondes
        updateCountdowns();
        setInterval(updateCountdowns, 1000);

        // Confirmation avant signature
        document.querySelectorAll('.signature-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!confirm('Êtes-vous sûr de vouloir signer votre présence ? Cette action ne peut pas être annulée.')) {
                    e.preventDefault();
                }
            });
        });

        // Auto-refresh de la page toutes les 30 secondes pour détecter les nouvelles sessions
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>

</html>