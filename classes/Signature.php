<?php
// classes/Signature.php

class Signature
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function record($id_utilisateur, $id_matiere, $signature, $horodatage)
    {
        try {
            $date = date('Y-m-d');
            $heure = date('H:i:s');

            // Vérification de la validité de la signature
            if (!$this->validateSignature($signature)) {
                throw new Exception("Signature invalide");
            }

            $stmt = $this->pdo->prepare("INSERT INTO signatures (id_utilisateur, id_matiere, date, heure, signature, horodatage)
                                       VALUES (:id_utilisateur, :id_matiere, :date, :heure, :signature, :horodatage)");

            $stmt->execute([
                ':id_utilisateur' => $id_utilisateur,
                ':id_matiere' => $id_matiere,
                ':date' => $date,
                ':heure' => $heure,
                ':signature' => $signature,
                ':horodatage' => $horodatage
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de l'enregistrement de la signature: " . $e->getMessage());
            return false;
        }
    }

    public function getSignaturesByUser($id_utilisateur)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, m.nom as matiere_nom, c.nom as classe_nom
                FROM signatures s
                JOIN matieres m ON s.id_matiere = m.id
                JOIN classes c ON m.id_classe = c.id
                WHERE s.id_utilisateur = :id_utilisateur
                ORDER BY s.date DESC, s.heure DESC
            ");

            $stmt->execute([':id_utilisateur' => $id_utilisateur]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des signatures: " . $e->getMessage());
            return [];
        }
    }

    public function getSignaturesByMatiere($id_matiere, $date)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, u.nom, u.prenom
                FROM signatures s
                JOIN utilisateurs u ON s.id_utilisateur = u.id
                WHERE s.id_matiere = :id_matiere
                AND s.date = :date
                ORDER BY s.heure DESC
            ");

            $stmt->execute([
                ':id_matiere' => $id_matiere,
                ':date' => $date
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des signatures par matière: " . $e->getMessage());
            return [];
        }
    }

    private function validateSignature($signature)
    {
        // Vérification du format de la signature (à adapter selon le format utilisé)
        return !empty($signature) && strlen($signature) > 10;
    }

    public function generateHorodatage()
    {
        return [
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s'),
            'hash' => hash('sha256', uniqid() . time())
        ];
    }

    public function verifySignature($signature, $horodatage)
    {
        // Vérification de l'intégrité de la signature et de l'horodatage
        return $this->validateSignature($signature) &&
            isset($horodatage['timestamp']) &&
            isset($horodatage['hash']);
    }
}
