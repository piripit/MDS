<?php
require_once '../config/config.php';
require_once '../classes/Database.php';

try {
    $pdo = Database::getInstance()->getConnection();

    // Vérifier si la table emplois_du_temps existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'emplois_du_temps'");
    if ($stmt->rowCount() == 0) {
        // Créer la table si elle n'existe pas
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS emplois_du_temps (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_classe INT NOT NULL,
                id_matiere INT NOT NULL,
                id_enseignant INT NOT NULL,
                jour INT NOT NULL,
                heure_debut TIME NOT NULL,
                heure_fin TIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_classe) REFERENCES classes(id),
                FOREIGN KEY (id_matiere) REFERENCES matieres(id),
                FOREIGN KEY (id_enseignant) REFERENCES utilisateurs(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Table 'emplois_du_temps' créée avec succès.\n";
    } else {
        echo "La table 'emplois_du_temps' existe déjà.\n";
    }
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
