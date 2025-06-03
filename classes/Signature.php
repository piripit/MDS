<?php
// classes/Signature.php

class Signature
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Générer un horodatage sécurisé
    public function generateHorodatage()
    {
        return [
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
    }

    // Enregistrer une signature de présence (nouvelle version)
    public function enregistrerSignature($id_eleve, $id_matiere, $id_enseignant, $statut, $retard_minutes = null, $commentaire = null)
    {
        try {
            // Vérifier si une signature existe déjà pour aujourd'hui
            $stmt = $this->pdo->prepare("
                SELECT id FROM signatures 
                WHERE id_eleve = ? AND id_matiere = ? AND date_signature = ?
            ");
            $stmt->execute([$id_eleve, $id_matiere, date('Y-m-d')]);

            if ($stmt->fetch()) {
                throw new Exception("Une signature existe déjà pour cet élève aujourd'hui");
            }

            // Insérer la nouvelle signature
            $stmt = $this->pdo->prepare("
                INSERT INTO signatures (id_eleve, id_matiere, id_enseignant, date_signature, heure_signature, statut, retard_minutes, commentaire)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $result = $stmt->execute([
                $id_eleve,
                $id_matiere,
                $id_enseignant,
                date('Y-m-d'),
                date('H:i:s'),
                $statut,
                $retard_minutes,
                $commentaire
            ]);

            return $result;
        } catch (Exception $e) {
            error_log("Erreur lors de l'enregistrement de la signature: " . $e->getMessage());
            throw $e;
        }
    }

    // Ancienne méthode record pour compatibilité
    public function record($id_utilisateur, $id_matiere, $signature, $horodatage)
    {
        try {
            $date = date('Y-m-d');
            $heure = date('H:i:s');

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

    // Récupérer les signatures d'un utilisateur (ancien système)
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

    // Récupérer les signatures d'une matière pour une date
    public function getSignaturesByMatiere($id_matiere, $date = null)
    {
        try {
            if (!$date) {
                $date = date('Y-m-d');
            }

            $stmt = $this->pdo->prepare("
                SELECT 
                    s.*,
                    u.nom,
                    u.prenom,
                    u.email,
                    m.nom as matiere_nom,
                    ens.nom as enseignant_nom,
                    ens.prenom as enseignant_prenom
                FROM signatures s
                INNER JOIN utilisateurs u ON s.id_eleve = u.id
                INNER JOIN matieres m ON s.id_matiere = m.id
                INNER JOIN utilisateurs ens ON s.id_enseignant = ens.id
                WHERE s.id_matiere = ? AND s.date_signature = ?
                ORDER BY s.heure_signature
            ");

            $stmt->execute([$id_matiere, $date]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des signatures: " . $e->getMessage());
            return [];
        }
    }

    // Récupérer les signatures d'un élève
    public function getSignaturesByEleve($id_eleve, $limite = 10)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    s.*,
                    m.nom as matiere_nom,
                    ens.nom as enseignant_nom,
                    ens.prenom as enseignant_prenom
                FROM signatures s
                INNER JOIN matieres m ON s.id_matiere = m.id
                INNER JOIN utilisateurs ens ON s.id_enseignant = ens.id
                WHERE s.id_eleve = ?
                ORDER BY s.date_signature DESC, s.heure_signature DESC
                LIMIT ?
            ");

            $stmt->execute([$id_eleve, $limite]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des signatures de l'élève: " . $e->getMessage());
            return [];
        }
    }

    // Récupérer les élèves d'une classe pour une matière
    public function getElevesClasse($id_matiere, $annee_scolaire = null)
    {
        try {
            if (!$annee_scolaire) {
                $annee_scolaire = date('Y') . '-' . (date('Y') + 1);
            }

            $stmt = $this->pdo->prepare("
                SELECT DISTINCT 
                    u.id,
                    u.nom,
                    u.prenom,
                    u.email,
                    c.nom as classe_nom,
                    c.niveau as classe_niveau
                FROM utilisateurs u
                INNER JOIN affectations_eleves ae ON u.id = ae.id_eleve
                INNER JOIN classes c ON ae.id_classe = c.id
                INNER JOIN matieres m ON c.id = m.id_classe
                WHERE u.role = 'eleve' 
                AND m.id = ? 
                AND ae.annee_scolaire = ?
                ORDER BY u.nom, u.prenom
            ");

            $stmt->execute([$id_matiere, $annee_scolaire]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des élèves: " . $e->getMessage());
            return [];
        }
    }

    // Vérifier si une session de signature est active
    public function isSessionActive($id_matiere, $id_enseignant)
    {
        try {
            $jour_actuel = date('N'); // 1=Lundi, 7=Dimanche
            $heure_actuelle = date('H:i:s');

            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM emploi_du_temps 
                WHERE id_matiere = ? 
                AND id_enseignant = ? 
                AND jour = ? 
                AND heure_debut <= ? 
                AND heure_fin >= ?
            ");

            $stmt->execute([$id_matiere, $id_enseignant, $jour_actuel, $heure_actuelle, $heure_actuelle]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Erreur lors de la vérification de session: " . $e->getMessage());
            return false;
        }
    }

    // Obtenir les statistiques de présence d'un élève
    public function getStatistiquesEleve($id_eleve)
    {
        try {
            $stats = [
                'total_cours' => 0,
                'presences' => 0,
                'absences' => 0,
                'retards' => 0,
                'taux_presence' => 0
            ];

            $stmt = $this->pdo->prepare("
                SELECT 
                    statut,
                    COUNT(*) as count
                FROM signatures 
                WHERE id_eleve = ? 
                GROUP BY statut
            ");
            $stmt->execute([$id_eleve]);
            $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($resultats as $result) {
                switch ($result['statut']) {
                    case 'present':
                        $stats['presences'] = $result['count'];
                        break;
                    case 'absent':
                        $stats['absences'] = $result['count'];
                        break;
                    case 'retard':
                        $stats['retards'] = $result['count'];
                        break;
                }
            }

            $stats['total_cours'] = $stats['presences'] + $stats['absences'] + $stats['retards'];

            if ($stats['total_cours'] > 0) {
                $stats['taux_presence'] = round(($stats['presences'] + $stats['retards']) / $stats['total_cours'] * 100, 2);
            }

            return $stats;
        } catch (Exception $e) {
            error_log("Erreur lors du calcul des statistiques: " . $e->getMessage());
            return $stats;
        }
    }

    // Validation d'une signature
    private function validateSignature($signature)
    {
        return !empty($signature) && strlen($signature) > 10;
    }

    // Vérifier la signature et l'horodatage
    public function verifySignature($signature, $horodatage)
    {
        return $this->validateSignature($signature) &&
            isset($horodatage['timestamp']);
    }

    // Ouvrir une session de signature
    public function ouvrirSession($id_matiere, $id_enseignant, $id_classe, $duree_minutes = 15)
    {
        try {
            // Fermer toute session active existante pour cette matière aujourd'hui
            $this->fermerSession($id_matiere, $id_enseignant);

            // Créer une nouvelle session
            $stmt = $this->pdo->prepare("
                INSERT INTO sessions_signature (id_matiere, id_enseignant, id_classe, date_session, heure_debut, duree_minutes, statut)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");

            $result = $stmt->execute([
                $id_matiere,
                $id_enseignant,
                $id_classe,
                date('Y-m-d'),
                date('H:i:s'),
                $duree_minutes
            ]);

            return $result;
        } catch (Exception $e) {
            error_log("Erreur lors de l'ouverture de session: " . $e->getMessage());
            throw $e;
        }
    }

    // Fermer une session de signature
    public function fermerSession($id_matiere, $id_enseignant)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE sessions_signature 
                SET statut = 'fermee', heure_fin = ?
                WHERE id_matiere = ? AND id_enseignant = ? AND date_session = ? AND statut = 'active'
            ");

            return $stmt->execute([date('H:i:s'), $id_matiere, $id_enseignant, date('Y-m-d')]);
        } catch (Exception $e) {
            error_log("Erreur lors de la fermeture de session: " . $e->getMessage());
            throw $e;
        }
    }

    // Vérifier si une session est active
    public function sessionActive($id_matiere, $id_enseignant)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, heure_debut, duree_minutes 
                FROM sessions_signature 
                WHERE id_matiere = ? AND id_enseignant = ? AND date_session = ? AND statut = 'active'
                LIMIT 1
            ");

            $stmt->execute([$id_matiere, $id_enseignant, date('Y-m-d')]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                return false;
            }

            // Vérifier si la session n'a pas expiré
            $heure_limite = date('H:i:s', strtotime($session['heure_debut'] . ' +' . $session['duree_minutes'] . ' minutes'));
            $heure_actuelle = date('H:i:s');

            if ($heure_actuelle > $heure_limite) {
                // Session expirée, la fermer automatiquement
                $this->fermerSession($id_matiere, $id_enseignant);
                return false;
            }

            return $session;
        } catch (Exception $e) {
            error_log("Erreur lors de la vérification de session: " . $e->getMessage());
            return false;
        }
    }

    // Signer sa présence (pour les élèves)
    public function signerPresence($id_eleve, $id_matiere, $commentaire = null)
    {
        try {
            // Vérifier qu'une session est active
            $stmt = $this->pdo->prepare("
                SELECT s.*, u.id as enseignant_id 
                FROM sessions_signature s
                INNER JOIN utilisateurs u ON s.id_enseignant = u.id
                WHERE s.id_matiere = ? AND s.date_session = ? AND s.statut = 'active'
                LIMIT 1
            ");

            $stmt->execute([$id_matiere, date('Y-m-d')]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                throw new Exception("Aucune session de signature active pour ce cours");
            }

            // Vérifier si l'élève n'a pas déjà signé
            $stmt = $this->pdo->prepare("
                SELECT id FROM signatures 
                WHERE id_eleve = ? AND id_matiere = ? AND date_signature = ?
            ");
            $stmt->execute([$id_eleve, $id_matiere, date('Y-m-d')]);

            if ($stmt->fetch()) {
                throw new Exception("Vous avez déjà signé votre présence pour ce cours aujourd'hui");
            }

            // Déterminer le statut (présent ou retard)
            $heure_cours = $session['heure_debut'];
            $heure_signature = date('H:i:s');
            $retard_minutes = 0;
            $statut = 'present';

            // Calculer le retard si applicable
            $diff = strtotime($heure_signature) - strtotime($heure_cours);
            if ($diff > 300) { // Plus de 5 minutes = retard
                $retard_minutes = floor($diff / 60);
                $statut = 'retard';
            }

            // Enregistrer la signature
            return $this->enregistrerSignature(
                $id_eleve,
                $id_matiere,
                $session['enseignant_id'],
                $statut,
                $retard_minutes > 0 ? $retard_minutes : null,
                $commentaire
            );
        } catch (Exception $e) {
            error_log("Erreur lors de la signature: " . $e->getMessage());
            throw $e;
        }
    }

    // Marquer automatiquement les absents à la fin d'une session
    public function marquerAbsents($id_matiere, $id_enseignant, $id_classe)
    {
        try {
            // Récupérer tous les élèves de la classe
            $eleves = $this->getElevesClasse($id_matiere);

            // Récupérer ceux qui ont déjà signé aujourd'hui
            $stmt = $this->pdo->prepare("
                SELECT id_eleve FROM signatures 
                WHERE id_matiere = ? AND date_signature = ?
            ");
            $stmt->execute([$id_matiere, date('Y-m-d')]);
            $eleves_presents = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $absents_marques = 0;

            // Marquer comme absents ceux qui n'ont pas signé
            foreach ($eleves as $eleve) {
                if (!in_array($eleve['id'], $eleves_presents)) {
                    $this->enregistrerSignature(
                        $eleve['id'],
                        $id_matiere,
                        $id_enseignant,
                        'absent',
                        null,
                        'Marqué automatiquement absent'
                    );
                    $absents_marques++;
                }
            }

            return $absents_marques;
        } catch (Exception $e) {
            error_log("Erreur lors du marquage des absents: " . $e->getMessage());
            throw $e;
        }
    }

    // Modifier le statut d'une signature existante
    public function modifierStatutSignature($id_signature, $nouveau_statut, $retard_minutes = null, $commentaire = null)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE signatures 
                SET statut = ?, retard_minutes = ?, commentaire = ?
                WHERE id = ?
            ");

            return $stmt->execute([$nouveau_statut, $retard_minutes, $commentaire, $id_signature]);
        } catch (Exception $e) {
            error_log("Erreur lors de la modification du statut: " . $e->getMessage());
            throw $e;
        }
    }

    // Obtenir les sessions actives d'un enseignant
    public function getSessionsActives($id_enseignant)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    s.*,
                    m.nom as matiere_nom,
                    c.nom as classe_nom,
                    c.niveau as classe_niveau
                FROM sessions_signature s
                INNER JOIN matieres m ON s.id_matiere = m.id
                INNER JOIN classes c ON s.id_classe = c.id
                WHERE s.id_enseignant = ? AND s.statut = 'active' AND s.date_session = ?
                ORDER BY s.heure_debut
            ");

            $stmt->execute([$id_enseignant, date('Y-m-d')]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des sessions actives: " . $e->getMessage());
            return [];
        }
    }
}
