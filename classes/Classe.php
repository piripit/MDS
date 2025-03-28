<?php
// classes/Classe.php

class Classe {
    public static function create($nom, $annee_scolaire) {
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO classes (nom, annee_scolaire) VALUES (:nom, :annee_scolaire)");
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':annee_scolaire', $annee_scolaire);
        $stmt->execute();
    }

    public static function getAll() {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM classes");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function modify($id, $nom, $annee_scolaire) {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE classes SET nom = :nom, annee_scolaire = :annee_scolaire WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
        $stmt->bindParam(':annee_scolaire', $annee_scolaire, PDO::PARAM_STR);
        $stmt->execute();
    }
}
?>
