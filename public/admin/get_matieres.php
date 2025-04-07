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

// Vérification du paramètre classe_id
if (!isset($_GET['classe_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètre classe_id manquant']);
    exit();
}

$classe_id = $_GET['classe_id'];

try {
    // Récupération des matières pour la classe sélectionnée
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.id, m.nom
        FROM matieres m
        JOIN matieres_classes mc ON m.id = mc.id_matiere
        WHERE mc.id_classe = :classe_id
        ORDER BY m.nom
    ");

    $stmt->execute([':classe_id' => $classe_id]);
    $matieres = $stmt->fetchAll();

    header('Content-Type: application/json');
    echo json_encode($matieres);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des matières: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des matières']);
}
