<?php
// classes/EmploiDuTemps.php

class EmploiDuTemps
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getMatieresByEnseignant($id_enseignant)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT m.*, c.nom as classe_nom
                FROM matieres m
                JOIN matieres_enseignants me ON m.id = me.id_matiere
                JOIN classes c ON m.id_classe = c.id
                WHERE me.id_enseignant = :id_enseignant
                ORDER BY c.nom, m.nom
            ");
            $stmt->execute([':id_enseignant' => $id_enseignant]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des matières: " . $e->getMessage());
            return [];
        }
    }

    public function getEnseignantsByMatiere($id_matiere)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT u.*
                FROM utilisateurs u
                JOIN matieres_enseignants me ON u.id = me.id_enseignant
                WHERE me.id_matiere = :id_matiere
                ORDER BY u.nom, u.prenom
            ");
            $stmt->execute([':id_matiere' => $id_matiere]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des enseignants: " . $e->getMessage());
            return [];
        }
    }

    public function getClassesByNiveau($niveau)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.*, 
                       COUNT(DISTINCT ae.id_eleve) as nombre_eleves,
                       CASE 
                           WHEN c.niveau = 'BTS' THEN CONCAT(c.nom, ' - ', c.option)
                           ELSE c.nom 
                       END as nom_complet
                FROM classes c
                LEFT JOIN affectations_eleves ae ON c.id = ae.id_classe
                WHERE c.niveau = :niveau
                GROUP BY c.id
                ORDER BY c.nom, c.option
            ");
            $stmt->execute([':niveau' => $niveau]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des classes: " . $e->getMessage());
            return [];
        }
    }

    public function getEmploiDuTempsByClasse($id_classe)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT edt.*, m.nom as matiere_nom, u.nom as enseignant_nom, u.prenom as enseignant_prenom
                FROM emploi_du_temps edt
                JOIN matieres m ON edt.id_matiere = m.id
                JOIN utilisateurs u ON m.id_enseignant = u.id
                WHERE edt.id_classe = :id_classe
                ORDER BY edt.jour, edt.heure
            ");

            $stmt->execute([':id_classe' => $id_classe]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération de l'emploi du temps: " . $e->getMessage());
            return [];
        }
    }

    public function getEmploiDuTempsByEnseignant($id_enseignant)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT edt.*, m.nom as matiere_nom, c.nom as classe_nom
                FROM emploi_du_temps edt
                JOIN matieres m ON edt.id_matiere = m.id
                JOIN classes c ON m.id_classe = c.id
                WHERE m.id_enseignant = :id_enseignant
                ORDER BY edt.jour, edt.heure
            ");

            $stmt->execute([':id_enseignant' => $id_enseignant]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération de l'emploi du temps: " . $e->getMessage());
            return [];
        }
    }

    public function addCreneau($id_classe, $id_matiere, $jour, $heure)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO emploi_du_temps (id_classe, id_matiere, jour, heure)
                VALUES (:id_classe, :id_matiere, :jour, :heure)
            ");

            return $stmt->execute([
                ':id_classe' => $id_classe,
                ':id_matiere' => $id_matiere,
                ':jour' => $jour,
                ':heure' => $heure
            ]);
        } catch (Exception $e) {
            error_log("Erreur lors de l'ajout du créneau: " . $e->getMessage());
            return false;
        }
    }

    public function deleteCreneau($id)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM emploi_du_temps WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression du créneau: " . $e->getMessage());
            return false;
        }
    }

    public function updateCreneau($id, $jour, $heure)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE emploi_du_temps
                SET jour = :jour, heure = :heure
                WHERE id = :id
            ");

            return $stmt->execute([
                ':id' => $id,
                ':jour' => $jour,
                ':heure' => $heure
            ]);
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour du créneau: " . $e->getMessage());
            return false;
        }
    }

    public function ajouterCours($id_classe, $id_matiere, $id_enseignant, $jour, $heure_debut, $heure_fin)
    {
        try {
            // Vérifier si l'enseignant a déjà 2 matières
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT id_matiere) as count 
                FROM matieres_enseignants 
                WHERE id_enseignant = :id_enseignant
            ");
            $stmt->execute([':id_enseignant' => $id_enseignant]);
            $result = $stmt->fetch();

            if ($result['count'] >= 2) {
                throw new Exception("Un enseignant ne peut pas enseigner plus de 2 matières.");
            }

            // Vérifier si le créneau est déjà occupé pour la classe
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
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
            $result = $stmt->fetch();

            if ($result['count'] > 0) {
                throw new Exception("Ce créneau horaire est déjà occupé pour cette classe.");
            }

            // Vérifier si le créneau est déjà occupé pour l'enseignant
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
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
            $result = $stmt->fetch();

            if ($result['count'] > 0) {
                throw new Exception("L'enseignant a déjà un cours à ce créneau horaire.");
            }

            // Ajouter le cours
            $stmt = $this->pdo->prepare("
                INSERT INTO emplois_du_temps (id_classe, id_matiere, id_enseignant, jour, heure_debut, heure_fin)
                VALUES (:id_classe, :id_matiere, :id_enseignant, :jour, :heure_debut, :heure_fin)
            ");

            return $stmt->execute([
                ':id_classe' => $id_classe,
                ':id_matiere' => $id_matiere,
                ':id_enseignant' => $id_enseignant,
                ':jour' => $jour,
                ':heure_debut' => $heure_debut,
                ':heure_fin' => $heure_fin
            ]);
        } catch (Exception $e) {
            error_log("Erreur lors de l'ajout du cours: " . $e->getMessage());
            throw $e;
        }
    }

    public function modifierCours($id, $id_classe, $id_matiere, $id_enseignant, $jour, $heure_debut, $heure_fin)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE emplois_du_temps 
                SET id_classe = :id_classe,
                    id_matiere = :id_matiere,
                    id_enseignant = :id_enseignant,
                    jour = :jour,
                    heure_debut = :heure_debut,
                    heure_fin = :heure_fin
                WHERE id = :id
            ");

            return $stmt->execute([
                ':id' => $id,
                ':id_classe' => $id_classe,
                ':id_matiere' => $id_matiere,
                ':id_enseignant' => $id_enseignant,
                ':jour' => $jour,
                ':heure_debut' => $heure_debut,
                ':heure_fin' => $heure_fin
            ]);
        } catch (Exception $e) {
            error_log("Erreur lors de la modification du cours: " . $e->getMessage());
            throw $e;
        }
    }

    public function supprimerCours($id)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM emplois_du_temps WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression du cours: " . $e->getMessage());
            throw $e;
        }
    }

    public function getCours()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT edt.*,
                       cl.nom as classe_nom,
                       cl.niveau as classe_niveau,
                       cl.option as classe_option,
                       m.nom as matiere_nom,
                       CONCAT(u.prenom, ' ', u.nom) as enseignant_nom
                FROM emploi_du_temps edt
                INNER JOIN classes cl ON edt.id_classe = cl.id
                INNER JOIN matieres m ON edt.id_matiere = m.id
                INNER JOIN utilisateurs u ON edt.id_enseignant = u.id
                ORDER BY edt.jour, edt.heure_debut
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des cours: " . $e->getMessage());
            return [];
        }
    }

    public function getCoursParClasse($id_classe)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT edt.*, 
                       m.nom as matiere_nom,
                       CONCAT(u.prenom, ' ', u.nom) as enseignant_nom
                FROM emploi_du_temps edt
                JOIN matieres m ON edt.id_matiere = m.id
                JOIN utilisateurs u ON edt.id_enseignant = u.id
                WHERE edt.id_classe = :id_classe
                ORDER BY edt.jour, edt.heure_debut
            ");
            $stmt->execute([':id_classe' => $id_classe]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des cours par classe: " . $e->getMessage());
            return [];
        }
    }

    public function getCoursParEnseignant($id_enseignant)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT edt.*, 
                       c.nom as classe_nom,
                       m.nom as matiere_nom
                FROM emploi_du_temps edt
                JOIN classes c ON edt.id_classe = c.id
                JOIN matieres m ON edt.id_matiere = m.id
                WHERE edt.id_enseignant = :id_enseignant
                ORDER BY edt.jour, edt.heure_debut
            ");
            $stmt->execute([':id_enseignant' => $id_enseignant]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des cours par enseignant: " . $e->getMessage());
            return [];
        }
    }
}
