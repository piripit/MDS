<?php
require_once '../config/config.php';
require_once '../classes/Auth.php';

session_start();

$auth = new Auth($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $user = $auth->login($email, $password);
        if ($user) {
            // Stocker les informations de l'utilisateur dans la session
            $_SESSION['user'] = $user;

            // Redirection selon le rôle
            switch ($user['role']) {
                case 'admin':
                    header('Location: admin/dashboard.php');
                    exit;
                case 'enseignant':
                    header('Location: enseignant/dashboard.php');
                    exit;
                case 'eleve':
                    header('Location: eleve/dashboard.php');
                    exit;
                default:
                    $error = "Rôle non reconnu";
            }
        } else {
            $error = "Email ou mot de passe incorrect";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Si l'utilisateur est déjà connecté, le rediriger
if (isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
    switch ($user['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            exit;
        case 'enseignant':
            header('Location: enseignant/dashboard.php');
            exit;
        case 'eleve':
            header('Location: eleve/dashboard.php');
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion de Classes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: #fff;
            border-bottom: none;
            text-align: center;
            padding: 20px;
        }

        .card-body {
            padding: 30px;
        }

        .btn-primary {
            width: 100%;
            padding: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="login-container">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Connexion</h3>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Se connecter</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>