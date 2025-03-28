<?php
require_once '../config/config.php';
require_once '../classes/Database.php';

try {
    $pdo = Database::getInstance()->getConnection();

    // Vérifier si la table classes existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'classes'");
    if ($stmt->rowCount() == 0) {
        // Créer la table si elle n'existe pas
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS classes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nom VARCHAR(50) NOT NULL,
                niveau ENUM('Seconde', 'Première', 'Terminale', 'BTS') NOT NULL,
                option VARCHAR(50),
                capacite INT NOT NULL DEFAULT 30,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Table 'classes' créée avec succès.\n";
    }

    // Vérifier s'il y a des classes
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM classes");
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        // Insérer des classes de test
        $pdo->exec("
            INSERT INTO classes (nom, niveau, option, capacite) VALUES
            ('2A', 'Seconde', NULL, 30),
            ('2B', 'Seconde', NULL, 30),
            ('1A', 'Première', NULL, 30),
            ('1B', 'Première', NULL, 30),
            ('TA', 'Terminale', NULL, 30),
            ('TB', 'Terminale', NULL, 30),
            ('BTS1', 'BTS', 'SLAM', 30),
            ('BTS2', 'BTS', 'SLAM', 30),
            ('BTS1', 'BTS', 'SISR', 30),
            ('BTS2', 'BTS', 'SISR', 30)
        ");
        echo "Classes de test insérées avec succès.\n";
    } else {
        echo "La table 'classes' contient déjà " . $result['count'] . " classes.\n";
    }

    // Afficher les classes existantes
    $stmt = $pdo->query("SELECT * FROM classes ORDER BY niveau, nom, option");
    $classes = $stmt->fetchAll();

    echo "\nListe des classes existantes :\n";
    foreach ($classes as $classe) {
        echo "- {$classe['nom']} ({$classe['niveau']}";
        if ($classe['option']) {
            echo " - {$classe['option']}";
        }
        echo ")\n";
    }
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
