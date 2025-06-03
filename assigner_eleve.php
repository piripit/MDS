<?php
require_once 'config/config.php';

echo "<h2>🎓 Assignation des élèves aux classes</h2>";

try {
    // Récupérer tous les élèves
    $stmt = $pdo->query("SELECT id, nom, prenom, email FROM utilisateurs WHERE role = 'eleve' ORDER BY nom, prenom");
    $eleves = $stmt->fetchAll();

    // Récupérer toutes les classes
    $stmt = $pdo->query("SELECT id, nom, niveau FROM classes ORDER BY niveau, nom");
    $classes = $stmt->fetchAll();

    if (empty($eleves)) {
        echo "❌ Aucun élève trouvé<br>";
        exit;
    }

    if (empty($classes)) {
        echo "❌ Aucune classe trouvée<br>";
        exit;
    }

    echo "<h3>📋 Élèves disponibles :</h3>";
    foreach ($eleves as $eleve) {
        echo "- {$eleve['prenom']} {$eleve['nom']} ({$eleve['email']})<br>";
    }

    echo "<br><h3>🏫 Classes disponibles :</h3>";
    foreach ($classes as $classe) {
        echo "- {$classe['niveau']} {$classe['nom']} (ID: {$classe['id']})<br>";
    }

    // Année scolaire actuelle
    $annee_scolaire = date('Y') . '-' . (date('Y') + 1);

    echo "<br><h3>🔗 Assignations automatiques pour l'année {$annee_scolaire} :</h3>";

    // Assigner chaque élève à la première classe disponible (ou à une classe spécifique)
    foreach ($eleves as $index => $eleve) {
        // Vérifier si l'élève est déjà assigné cette année
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM affectations_eleves 
            WHERE id_eleve = ? AND annee_scolaire = ?
        ");
        $stmt->execute([$eleve['id'], $annee_scolaire]);
        $deja_assigne = $stmt->fetchColumn() > 0;

        if ($deja_assigne) {
            echo "✅ {$eleve['prenom']} {$eleve['nom']} est déjà assigné à une classe<br>";
        } else {
            // Assigner à la première classe (ou vous pouvez choisir une logique différente)
            $classe_assignee = $classes[0]; // Première classe disponible

            $stmt = $pdo->prepare("
                INSERT INTO affectations_eleves (id_eleve, id_classe, annee_scolaire) 
                VALUES (?, ?, ?)
            ");
            $result = $stmt->execute([$eleve['id'], $classe_assignee['id'], $annee_scolaire]);

            if ($result) {
                echo "✅ {$eleve['prenom']} {$eleve['nom']} assigné à {$classe_assignee['niveau']} {$classe_assignee['nom']}<br>";
            } else {
                echo "❌ Erreur lors de l'assignation de {$eleve['prenom']} {$eleve['nom']}<br>";
            }
        }
    }

    echo "<br><div style='background: #d4edda; padding: 20px; border-radius: 5px; border-left: 5px solid #28a745;'>";
    echo "<h3>🎉 Assignations terminées !</h3>";
    echo "<p>Tous les élèves sont maintenant assignés à leurs classes.</p>";
    echo "<p>Ils peuvent maintenant accéder à leur emploi du temps dans leur espace personnel.</p>";
    echo "<p><strong>Note :</strong> Si vous voulez changer l'assignation d'un élève, vous pouvez le faire via l'espace admin → Élèves.</p>";
    echo "</div>";
} catch (PDOException $e) {
    echo "❌ Erreur de base de données : " . $e->getMessage() . "<br>";
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "<br>";
}
