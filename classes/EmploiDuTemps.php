<?php
// classes/EmploiDuTemps.php

class EmploiDuTemps
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Récupérer toutes les classes
    public function getClasses()
    {
        $stmt = $this->pdo->query("
            SELECT id, nom, niveau
            FROM classes 
            ORDER BY niveau, nom
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer toutes les matières
    public function getMatieres()
    {
        $stmt = $this->pdo->query("
            SELECT id, nom 
            FROM matieres 
            ORDER BY nom
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer tous les enseignants
    public function getEnseignants()
    {
        $stmt = $this->pdo->query("
            SELECT id, nom, prenom 
            FROM utilisateurs 
            WHERE role = 'enseignant' 
            ORDER BY nom, prenom
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer tous les cours
    public function getCours()
    {
        $stmt = $this->pdo->query("
            SELECT 
                edt.id,
                edt.jour,
                edt.heure_debut,
                edt.heure_fin,
                c.id as id_classe,
                c.nom as classe_nom,
                c.niveau as classe_niveau,
                m.id as id_matiere,
                m.nom as matiere_nom,
                u.id as id_enseignant,
                u.nom as enseignant_nom,
                u.prenom as enseignant_prenom
            FROM emploi_du_temps edt
            LEFT JOIN classes c ON edt.id_classe = c.id
            LEFT JOIN matieres m ON edt.id_matiere = m.id
            LEFT JOIN utilisateurs u ON edt.id_enseignant = u.id
            ORDER BY edt.jour, edt.heure_debut
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ajouter un cours
    public function ajouterCours($id_classe, $id_matiere, $id_enseignant, $jour, $heure_debut, $heure_fin)
    {
        try {
            // Vérification des conflits
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM emploi_du_temps 
                WHERE id_classe = :id_classe 
                AND jour = :jour 
                AND (
                    (heure_debut <= :heure_debut AND heure_fin > :heure_debut) OR
                    (heure_debut < :heure_fin AND heure_fin >= :heure_fin) OR
                    (heure_debut >= :heure_debut AND heure_fin <= :heure_fin)
                )
            ");
            $stmt->execute([
                ':id_classe' => $id_classe,
                ':jour' => $jour,
                ':heure_debut' => $heure_debut,
                ':heure_fin' => $heure_fin
            ]);

            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Il y a un conflit d'horaire pour cette classe.");
            }

            // Vérification des conflits pour l'enseignant
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM emploi_du_temps 
                WHERE id_enseignant = :id_enseignant 
                AND jour = :jour 
                AND (
                    (heure_debut <= :heure_debut AND heure_fin > :heure_debut) OR
                    (heure_debut < :heure_fin AND heure_fin >= :heure_fin) OR
                    (heure_debut >= :heure_debut AND heure_fin <= :heure_fin)
                )
            ");
            $stmt->execute([
                ':id_enseignant' => $id_enseignant,
                ':jour' => $jour,
                ':heure_debut' => $heure_debut,
                ':heure_fin' => $heure_fin
            ]);

            if ($stmt->fetchColumn() > 0) {
                throw new Exception("L'enseignant a déjà un cours à cette heure.");
            }

            // Insertion du cours
            $stmt = $this->pdo->prepare("
                INSERT INTO emploi_du_temps (id_classe, id_matiere, id_enseignant, jour, heure_debut, heure_fin) 
                VALUES (:id_classe, :id_matiere, :id_enseignant, :jour, :heure_debut, :heure_fin)
            ");

            $stmt->execute([
                ':id_classe' => $id_classe,
                ':id_matiere' => $id_matiere,
                ':id_enseignant' => $id_enseignant,
                ':jour' => $jour,
                ':heure_debut' => $heure_debut,
                ':heure_fin' => $heure_fin
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de l'ajout du cours: " . $e->getMessage());
            throw $e;
        }
    }

    // Modifier un cours
    public function modifierCours($id, $id_classe, $id_matiere, $id_enseignant, $jour, $heure_debut, $heure_fin)
    {
        try {
            // Vérification des conflits pour la classe
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM emploi_du_temps 
                WHERE id != :id
                AND id_classe = :id_classe 
                AND jour = :jour 
                AND (
                    (heure_debut <= :heure_debut AND heure_fin > :heure_debut) OR
                    (heure_debut < :heure_fin AND heure_fin >= :heure_fin) OR
                    (heure_debut >= :heure_debut AND heure_fin <= :heure_fin)
                )
            ");
            $stmt->execute([
                ':id' => $id,
                ':id_classe' => $id_classe,
                ':jour' => $jour,
                ':heure_debut' => $heure_debut,
                ':heure_fin' => $heure_fin
            ]);

            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Il y a un conflit d'horaire pour cette classe.");
            }

            // Vérification des conflits pour l'enseignant
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM emploi_du_temps 
                WHERE id != :id
                AND id_enseignant = :id_enseignant 
                AND jour = :jour 
                AND (
                    (heure_debut <= :heure_debut AND heure_fin > :heure_debut) OR
                    (heure_debut < :heure_fin AND heure_fin >= :heure_fin) OR
                    (heure_debut >= :heure_debut AND heure_fin <= :heure_fin)
                )
            ");
            $stmt->execute([
                ':id' => $id,
                ':id_enseignant' => $id_enseignant,
                ':jour' => $jour,
                ':heure_debut' => $heure_debut,
                ':heure_fin' => $heure_fin
            ]);

            if ($stmt->fetchColumn() > 0) {
                throw new Exception("L'enseignant a déjà un cours à cette heure.");
            }

            // Modification du cours
            $stmt = $this->pdo->prepare("
                UPDATE emploi_du_temps 
                SET id_classe = :id_classe,
                    id_matiere = :id_matiere,
                    id_enseignant = :id_enseignant,
                    jour = :jour,
                    heure_debut = :heure_debut,
                    heure_fin = :heure_fin
                WHERE id = :id
            ");

            $stmt->execute([
                ':id' => $id,
                ':id_classe' => $id_classe,
                ':id_matiere' => $id_matiere,
                ':id_enseignant' => $id_enseignant,
                ':jour' => $jour,
                ':heure_debut' => $heure_debut,
                ':heure_fin' => $heure_fin
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de la modification du cours: " . $e->getMessage());
            throw $e;
        }
    }

    // Supprimer un cours
    public function supprimerCours($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM emploi_du_temps WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function getEmploiDuTempsByClasse($id_classe)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT edt.*, 
                       c.nom as classe_nom,
                       m.nom as matiere_nom,
                       u.nom as enseignant_nom,
                       u.prenom as enseignant_prenom
                FROM emploi_du_temps edt
                LEFT JOIN classes c ON edt.id_classe = c.id
                LEFT JOIN matieres m ON edt.id_matiere = m.id
                LEFT JOIN utilisateurs u ON edt.id_enseignant = u.id
                WHERE edt.id_classe = :id_classe
                ORDER BY edt.jour, edt.heure_debut
            ");
            $stmt->execute([':id_classe' => $id_classe]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération de l'emploi du temps: " . $e->getMessage());
            return [];
        }
    }
}
