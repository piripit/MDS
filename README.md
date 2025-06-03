# MDS - Management des Signatures

## Description

Bienvenu Formation est une application web de gestion des signatures et des présences dans un établissement scolaire. 
Elle permet aux enseignants de gérer les signatures des élèves, 
aux élèves de signer leur présence, 
et aux administrateurs de gérer l'ensemble du système.

## Fonctionnalités

### Pour les Administrateurs

- Gestion des utilisateurs (enseignants, élèves)
- Gestion des classes
- Gestion des matières
- Gestion de l'emploi du temps
- Gestion des paramètres du système
- Visualisation des statistiques

### Pour les Enseignants

- Gestion des signatures des élèves
- Consultation de l'emploi du temps
- Gestion des présences
- Visualisation des statistiques de classe

### Pour les Élèves

- Signature de présence
- Consultation de l'emploi du temps
- Historique des signatures
- Visualisation des statistiques personnelles

## Prérequis

- PHP 8.0 ou supérieur
- MySQL 5.7 ou supérieur
- Serveur web (Apache)
- MAMP (pour le développement local)

## Installation

1. Cloner le repository :

```bash
git clone https://github.com/piripit/MDS.git
```

2. Configurer la base de données :

   - Créer une base de données MySQL nommée "gestion_classes"
   - Importer le fichier `sql/init_db.php`
   - Configurer les paramètres de connexion dans `config/config.php`

3. Configurer le serveur web :

   - Pointer le document root vers le dossier du projet
   - S'assurer que le module mod_rewrite est activé pour Apache
   - Configurer les permissions des dossiers (notamment pour les uploads)

4. Accéder à l'application :
   - Ouvrir un navigateur et accéder à `http://localhost/MDS`
   - Se connecter avec les identifiants par défaut :
     - Email : admin@mds.com
     - Mot de passe : admin123

## Structure du Projet

```
Bienvenu Formation/
├── config/
│   └── config.php
├── classes/
│   ├── Auth.php
│   ├── Database.php
│   ├── EmploiDuTemps.php
│   ├── Mailer.php
│   └── Signature.php
├── public/
│   ├── admin/
│   ├── enseignant/
│   └── eleve/
├── sql/
│   ├── init_db.php
│   └── emploi_du_temps.sql
└── README.md
```

## Configuration

Le fichier `config/config.php` contient les paramètres de configuration :

- Paramètres de la base de données
- Paramètres SMTP pour l'envoi d'emails
- Paramètres de l'application

## Sécurité

- Authentification sécurisée avec hachage des mots de passe
- Protection contre les injections SQL
- Gestion des sessions
- Validation des données
- Protection CSRF

## Maintenance

- Sauvegardes régulières de la base de données
- Logs d'erreurs
- Nettoyage automatique des sessions expirées

## Support

Pour toute question ou problème :

- Créer une issue sur GitHub
- Contacter l'administrateur système
- Consulter la documentation utilisateur

## Contribution

Les contributions sont les bienvenues ! N'hésitez pas à :

1. Fork le projet
2. Créer une branche pour votre fonctionnalité
3. Commiter vos changements
4. Pousser vers la branche
5. Ouvrir une Pull Request

## Auteurs

- [FIATUWO Moriel] - Développement initial
- [My Digital School] - Soutien technique

## Remerciements

- Bootstrap pour le design
- jQuery pour les interactions JavaScript
- PHP pour le backend
- MySQL pour la base de données
