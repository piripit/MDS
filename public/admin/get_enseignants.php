<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';

session_start();

// Vérification de l'authentification et du rôle
if (!Auth::isLoggedIn() || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    exit();
}

// Vérification du paramètre matiere_id
if (!isset($_GET['matiere_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètre matiere_id manquant']);
    exit();
}

$matiere_id = $_GET['matiere_id'];

try {
    // Récupération des enseignants pour la matière sélectionnée
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.nom, u.prenom
        FROM utilisateurs u
        JOIN matieres_enseignants me ON u.id = me.id_enseignant
        WHERE u.role = 'enseignant'
        AND me.id_matiere = :matiere_id
        ORDER BY u.nom, u.prenom
    ");

    $stmt->execute([':matiere_id' => $matiere_id]);
    $enseignants = $stmt->fetchAll();

    header('Content-Type: application/json');
    echo json_encode($enseignants);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des enseignants: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des enseignants']);
}
