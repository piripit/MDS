<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';

session_start();

// Vérification de l'authentification et du rôle
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit('Accès non autorisé');
}

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    exit('ID de cours manquant');
}

try {
    $cours_id = $_GET['id'];

    // Récupération des détails du cours
    $stmt = $pdo->prepare("
        SELECT edt.*, 
               c.id as id_classe, c.nom as classe_nom, c.niveau as classe_niveau, c.option as classe_option,
               m.id as id_matiere, m.nom as matiere_nom,
               e.id as id_enseignant, e.nom as enseignant_nom, e.prenom as enseignant_prenom
        FROM emplois_du_temps edt
        LEFT JOIN classes c ON edt.id_classe = c.id
        LEFT JOIN matieres m ON edt.id_matiere = m.id
        LEFT JOIN utilisateurs e ON edt.id_enseignant = e.id
        WHERE edt.id = :cours_id
    ");

    $stmt->execute(['cours_id' => $cours_id]);
    $cours = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cours) {
        http_response_code(404);
        echo json_encode(['error' => 'Cours non trouvé']);
        exit;
    }

    echo json_encode($cours);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération du cours: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération du cours']);
}
