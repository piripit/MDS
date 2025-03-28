<?php
require_once '../../config/config.php';
require_once '../../classes/Database.php';

header('Content-Type: application/json');

try {
    $pdo = Database::getInstance()->getConnection();

    // Récupération de tous les enseignants
    $stmt = $pdo->query("
        SELECT DISTINCT u.* 
        FROM utilisateurs u
        WHERE u.role = 'enseignant'
        ORDER BY u.prenom, u.nom
    ");

    $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($enseignants);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des enseignants: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur lors de la récupération des enseignants']);
}
