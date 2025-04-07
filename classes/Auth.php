<?php
// classes/Auth.php

class Auth
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public static function isLoggedIn()
    {
        return isset($_SESSION['user']);
    }

    public function login($email, $password)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM utilisateurs 
                WHERE email = :email
            ");

            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                unset($user['password']); // Ne pas stocker le mot de passe en session
                return $user; // Retourner les informations de l'utilisateur
            }

            return null; // Retourner null si l'authentification échoue
        } catch (Exception $e) {
            error_log("Erreur lors de la connexion: " . $e->getMessage());
            return null;
        }
    }

    public function logout()
    {
        session_destroy();
        return true;
    }

    public function register($nom, $prenom, $email, $password, $role)
    {
        try {
            // Vérifier si l'email existe déjà
            $stmt = $this->pdo->prepare("SELECT id FROM utilisateurs WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                return false;
            }

            // Créer le nouvel utilisateur
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("
                INSERT INTO utilisateurs (nom, prenom, email, password, role)
                VALUES (:nom, :prenom, :email, :password, :role)
            ");

            return $stmt->execute([
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':role' => $role
            ]);
        } catch (Exception $e) {
            error_log("Erreur lors de l'inscription: " . $e->getMessage());
            return false;
        }
    }

    public function resetPassword($email)
    {
        try {
            // Générer un token unique
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Sauvegarder le token
            $stmt = $this->pdo->prepare("
                UPDATE utilisateurs 
                SET reset_token = :token, reset_expiry = :expiry
                WHERE email = :email
            ");

            $stmt->execute([
                ':token' => $token,
                ':expiry' => $expiry,
                ':email' => $email
            ]);

            // TODO: Envoyer l'email avec le lien de réinitialisation
            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de la réinitialisation du mot de passe: " . $e->getMessage());
            return false;
        }
    }

    public function updatePassword($token, $newPassword)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE utilisateurs 
                SET password = :password, reset_token = NULL, reset_expiry = NULL
                WHERE reset_token = :token AND reset_expiry > NOW()
            ");

            return $stmt->execute([
                ':password' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':token' => $token
            ]);
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour du mot de passe: " . $e->getMessage());
            return false;
        }
    }

    public function getUserById($id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, nom, prenom, email, role 
                FROM utilisateurs 
                WHERE id = :id
            ");

            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération de l'utilisateur: " . $e->getMessage());
            return false;
        }
    }
}
