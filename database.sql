-- Active: 1742056345167@@127.0.0.1@3306@gestion_classes
-- Création de la base de données
CREATE DATABASE IF NOT EXISTS gestion_classes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestion_classes;

-- Table des utilisateurs (élèves et enseignants)
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'enseignant', 'eleve') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table des classes
CREATE TABLE IF NOT EXISTS classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(50) NOT NULL,
    niveau VARCHAR(20) NOT NULL,
    capacite INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table des matières
CREATE TABLE IF NOT EXISTS matieres (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    id_classe INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_classe) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table de liaison entre matières et enseignants
CREATE TABLE IF NOT EXISTS matieres_enseignants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_matiere INT NOT NULL,
    id_enseignant INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_matiere) REFERENCES matieres(id) ON DELETE CASCADE,
    FOREIGN KEY (id_enseignant) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_matiere_enseignant (id_matiere, id_enseignant)
) ENGINE=InnoDB;

-- Table des affectations des élèves aux classes
CREATE TABLE IF NOT EXISTS affectations_eleves (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_eleve INT NOT NULL,
    id_classe INT NOT NULL,
    annee_scolaire VARCHAR(9) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_eleve) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (id_classe) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_eleve_annee (id_eleve, annee_scolaire)
) ENGINE=InnoDB;



-- Table des signatures de présence
CREATE TABLE IF NOT EXISTS signatures (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_eleve INT NOT NULL,
    id_matiere INT NOT NULL,
    id_enseignant INT NOT NULL,
    date_signature DATE NOT NULL,
    heure_signature TIME NOT NULL,
    statut ENUM('present', 'absent', 'retard') NOT NULL,
    retard_minutes INT,
    commentaire TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_eleve) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (id_matiere) REFERENCES matieres(id) ON DELETE CASCADE,
    FOREIGN KEY (id_enseignant) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_signature_jour (id_eleve, id_matiere, date_signature)
) ENGINE=InnoDB;

-- Table emploi_du_temps
CREATE TABLE IF NOT EXISTS emploi_du_temps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_classe INT NOT NULL,
    id_matiere INT NOT NULL,
    id_enseignant INT NOT NULL,
    jour INT NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    FOREIGN KEY (id_classe) REFERENCES classes(id),
    FOREIGN KEY (id_matiere) REFERENCES matieres(id),
    FOREIGN KEY (id_enseignant) REFERENCES utilisateurs(id),
    UNIQUE KEY unique_creneau (id_classe, jour, heure_debut, heure_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion d'un administrateur par défaut
INSERT INTO utilisateurs (nom, prenom, email, password, role) VALUES 
('Admin', 'System', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Création des index pour optimiser les performances
CREATE INDEX idx_utilisateurs_role ON utilisateurs(role);
CREATE INDEX idx_affectations_eleves_annee ON affectations_eleves(annee_scolaire);
CREATE INDEX idx_signatures_date ON signatures(date_signature);
CREATE INDEX idx_emplois_du_temps_jour ON emplois_du_temps(jour);

-- Ajout d'une contrainte pour limiter le nombre de matières par enseignant
ALTER TABLE matieres_enseignants
ADD CONSTRAINT max_matieres_enseignant
CHECK (
    (SELECT COUNT(*) FROM matieres_enseignants WHERE id_enseignant = matieres_enseignants.id_enseignant) <= 2
); 