<?php
require_once '../../config/config.php';
require_once '../../classes/Database.php';

header('Content-Type: application/json');

try {
    $pdo = Database::getInstance()->getConnection();

    // Récupération de toutes les matières
    $stmt = $pdo->query("
        SELECT DISTINCT m.* 
        FROM matieres m
        ORDER BY m.nom
    ");

    $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($matieres);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des matières: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur lors de la récupération des matières']);
}
