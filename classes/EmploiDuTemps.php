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
        try {
            $stmt = $this->pdo->query("
                SELECT id, nom, niveau, capacite
                FROM classes 
                ORDER BY niveau, nom
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des classes: " . $e->getMessage());
            return [];
        }
    }

    // Récupérer toutes les matières
    public function getMatieres()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT id, nom, description
                FROM matieres 
                ORDER BY nom
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des matières: " . $e->getMessage());
            return [];
        }
    }

    // Récupérer tous les enseignants
    public function getEnseignants()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT id, nom, prenom, email
                FROM utilisateurs 
                WHERE role = 'enseignant' 
                ORDER BY nom, prenom
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des enseignants: " . $e->getMessage());
            return [];
        }
    }

    // Récupérer tous les cours
    public function getCours()
    {
        try {
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
                INNER JOIN classes c ON edt.id_classe = c.id
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

    // Vérifier les conflits d'horaire pour une classe
    private function verifierConflitClasse($id_classe, $jour, $heure_debut, $heure_fin, $id_cours = null)
    {
        try {
            $sql = "
                SELECT COUNT(*) 
                FROM emploi_du_temps 
                WHERE id_classe = ? 
                AND jour = ? 
                AND (
                    (heure_debut < ? AND heure_fin > ?) OR
                    (heure_debut < ? AND heure_fin > ?) OR
                    (heure_debut >= ? AND heure_debut < ?) OR
                    (heure_fin > ? AND heure_fin <= ?)
                )
            ";

            $params = [$id_classe, $jour, $heure_fin, $heure_debut, $heure_debut, $heure_debut, $heure_debut, $heure_fin, $heure_debut, $heure_fin];

            if ($id_cours !== null) {
                $sql .= " AND id != ?";
                $params[] = $id_cours;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Erreur lors de la vérification de conflit classe: " . $e->getMessage());
            throw new Exception("Erreur lors de la vérification des conflits");
        }
    }

    // Vérifier les conflits d'horaire pour un enseignant
    private function verifierConflitEnseignant($id_enseignant, $jour, $heure_debut, $heure_fin, $id_cours = null)
    {
        try {
            $sql = "
                SELECT COUNT(*) 
                FROM emploi_du_temps 
                WHERE id_enseignant = ? 
                AND jour = ? 
                AND (
                    (heure_debut < ? AND heure_fin > ?) OR
                    (heure_debut < ? AND heure_fin > ?) OR
                    (heure_debut >= ? AND heure_debut < ?) OR
                    (heure_fin > ? AND heure_fin <= ?)
                )
            ";

            $params = [$id_enseignant, $jour, $heure_fin, $heure_debut, $heure_debut, $heure_debut, $heure_debut, $heure_fin, $heure_debut, $heure_fin];

            if ($id_cours !== null) {
                $sql .= " AND id != ?";
                $params[] = $id_cours;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Erreur lors de la vérification de conflit enseignant: " . $e->getMessage());
            throw new Exception("Erreur lors de la vérification des conflits");
        }
    }

    // Ajouter un cours
    public function ajouterCours($id_classe, $id_matiere, $id_enseignant, $jour, $heure_debut, $heure_fin)
    {
        try {
            // Validation des données
            if (empty($id_classe) || empty($id_matiere) || empty($id_enseignant) || empty($jour) || empty($heure_debut) || empty($heure_fin)) {
                throw new Exception("Tous les champs sont obligatoires");
            }

            if ($heure_debut >= $heure_fin) {
                throw new Exception("L'heure de fin doit être après l'heure de début");
            }

            // Vérification des conflits pour la classe
            if ($this->verifierConflitClasse($id_classe, $jour, $heure_debut, $heure_fin)) {
                throw new Exception("Il y a un conflit d'horaire pour cette classe à ce créneau");
            }

            // Vérification des conflits pour l'enseignant
            if ($this->verifierConflitEnseignant($id_enseignant, $jour, $heure_debut, $heure_fin)) {
                throw new Exception("L'enseignant a déjà un cours à ce créneau");
            }

            // Insertion du cours
            $stmt = $this->pdo->prepare("
                INSERT INTO emploi_du_temps (id_classe, id_matiere, id_enseignant, jour, heure_debut, heure_fin) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $result = $stmt->execute([$id_classe, $id_matiere, $id_enseignant, $jour, $heure_debut, $heure_fin]);

            if (!$result) {
                throw new Exception("Erreur lors de l'insertion du cours");
            }

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
            // Validation des données
            if (empty($id) || empty($id_classe) || empty($id_matiere) || empty($id_enseignant) || empty($jour) || empty($heure_debut) || empty($heure_fin)) {
                throw new Exception("Tous les champs sont obligatoires");
            }

            if ($heure_debut >= $heure_fin) {
                throw new Exception("L'heure de fin doit être après l'heure de début");
            }

            // Vérification des conflits pour la classe (en excluant le cours actuel)
            if ($this->verifierConflitClasse($id_classe, $jour, $heure_debut, $heure_fin, $id)) {
                throw new Exception("Il y a un conflit d'horaire pour cette classe à ce créneau");
            }

            // Vérification des conflits pour l'enseignant (en excluant le cours actuel)
            if ($this->verifierConflitEnseignant($id_enseignant, $jour, $heure_debut, $heure_fin, $id)) {
                throw new Exception("L'enseignant a déjà un cours à ce créneau");
            }

            // Modification du cours
            $stmt = $this->pdo->prepare("
                UPDATE emploi_du_temps 
                SET id_classe = ?, id_matiere = ?, id_enseignant = ?, jour = ?, heure_debut = ?, heure_fin = ?
                WHERE id = ?
            ");

            $result = $stmt->execute([$id_classe, $id_matiere, $id_enseignant, $jour, $heure_debut, $heure_fin, $id]);

            if (!$result) {
                throw new Exception("Erreur lors de la modification du cours");
            }

            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de la modification du cours: " . $e->getMessage());
            throw $e;
        }
    }

    // Supprimer un cours
    public function supprimerCours($id)
    {
        try {
            if (empty($id)) {
                throw new Exception("ID du cours manquant");
            }

            $stmt = $this->pdo->prepare("DELETE FROM emploi_du_temps WHERE id = ?");
            $result = $stmt->execute([$id]);

            if (!$result) {
                throw new Exception("Erreur lors de la suppression du cours");
            }

            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression du cours: " . $e->getMessage());
            throw $e;
        }
    }

    // Récupérer l'emploi du temps d'une classe
    public function getEmploiDuTempsByClasse($id_classe)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    edt.*,
                    c.nom as classe_nom,
                    c.niveau as classe_niveau,
                    m.nom as matiere_nom,
                    u.nom as enseignant_nom,
                    u.prenom as enseignant_prenom
                FROM emploi_du_temps edt
                INNER JOIN classes c ON edt.id_classe = c.id
                INNER JOIN matieres m ON edt.id_matiere = m.id
                INNER JOIN utilisateurs u ON edt.id_enseignant = u.id
                WHERE edt.id_classe = ?
                ORDER BY edt.jour, edt.heure_debut
            ");
            $stmt->execute([$id_classe]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération de l'emploi du temps: " . $e->getMessage());
            return [];
        }
    }

    // Récupérer l'emploi du temps d'un enseignant
    public function getEmploiDuTempsByEnseignant($id_enseignant)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    edt.*,
                    c.nom as classe_nom,
                    c.niveau as classe_niveau,
                    m.nom as matiere_nom
                FROM emploi_du_temps edt
                INNER JOIN classes c ON edt.id_classe = c.id
                INNER JOIN matieres m ON edt.id_matiere = m.id
                WHERE edt.id_enseignant = ?
                ORDER BY edt.jour, edt.heure_debut
            ");
            $stmt->execute([$id_enseignant]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération de l'emploi du temps: " . $e->getMessage());
            return [];
        }
    }

    // Obtenir un cours par son ID
    public function getCoursById($id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    edt.*,
                    c.nom as classe_nom,
                    c.niveau as classe_niveau,
                    m.nom as matiere_nom,
                    u.nom as enseignant_nom,
                    u.prenom as enseignant_prenom
                FROM emploi_du_temps edt
                INNER JOIN classes c ON edt.id_classe = c.id
                INNER JOIN matieres m ON edt.id_matiere = m.id
                INNER JOIN utilisateurs u ON edt.id_enseignant = u.id
                WHERE edt.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération du cours: " . $e->getMessage());
            return false;
        }
    }

    // Récupérer les matières enseignées par un enseignant
    public function getMatieresByEnseignant($id_enseignant)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT 
                    m.id,
                    m.nom,
                    m.description,
                    c.nom as classe_nom,
                    c.niveau as classe_niveau
                FROM matieres m
                INNER JOIN matieres_enseignants me ON m.id = me.id_matiere
                INNER JOIN classes c ON m.id_classe = c.id
                WHERE me.id_enseignant = ?
                ORDER BY c.niveau, c.nom, m.nom
            ");
            $stmt->execute([$id_enseignant]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des matières de l'enseignant: " . $e->getMessage());
            return [];
        }
    }
}
