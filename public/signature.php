<?php
require_once '../config/config.php';
require_once '../classes/Auth.php';
require_once '../classes/Signature.php';

session_start();

// Vérification de l'authentification
if (!Auth::isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$signature = new Signature($pdo);
$user = $_SESSION['user'];

// Traitement de la soumission de la signature
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signature_data'])) {
    $signature_data = $_POST['signature_data'];
    $id_matiere = $_POST['id_matiere'];
    $horodatage = $signature->generateHorodatage();

    if ($signature->record($user['id'], $id_matiere, $signature_data, json_encode($horodatage))) {
        $_SESSION['success'] = "Signature enregistrée avec succès";
    } else {
        $_SESSION['error'] = "Erreur lors de l'enregistrement de la signature";
    }

    header('Location: signature.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signature Électronique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .signature-pad {
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #fff;
        }

        .signature-controls {
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <h2>Signature Électronique</h2>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error'];
                unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form id="signatureForm" method="POST">
                    <input type="hidden" name="id_matiere" value="<?php echo $_GET['matiere'] ?? ''; ?>">
                    <input type="hidden" name="signature_data" id="signature_data">

                    <div class="mb-3">
                        <label for="signaturePad" class="form-label">Veuillez signer ci-dessous</label>
                        <canvas id="signaturePad" class="signature-pad" width="500" height="200"></canvas>
                    </div>

                    <div class="signature-controls">
                        <button type="button" class="btn btn-secondary" id="clearSignature">Effacer</button>
                        <button type="submit" class="btn btn-primary" id="submitSignature">Valider la signature</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('signaturePad');
            const ctx = canvas.getContext('2d');
            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;

            // Configuration du canvas
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            // Gestion des événements de dessin
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseout', stopDrawing);

            // Support tactile
            canvas.addEventListener('touchstart', handleTouch);
            canvas.addEventListener('touchmove', handleTouch);
            canvas.addEventListener('touchend', stopDrawing);

            function startDrawing(e) {
                isDrawing = true;
                [lastX, lastY] = getCoordinates(e);
            }

            function draw(e) {
                if (!isDrawing) return;
                e.preventDefault();

                const [currentX, currentY] = getCoordinates(e);

                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(currentX, currentY);
                ctx.stroke();

                [lastX, lastY] = [currentX, currentY];
            }

            function stopDrawing() {
                isDrawing = false;
            }

            function getCoordinates(e) {
                if (e.type.includes('touch')) {
                    const touch = e.touches[0];
                    const rect = canvas.getBoundingClientRect();
                    return [
                        touch.clientX - rect.left,
                        touch.clientY - rect.top
                    ];
                }
                return [e.offsetX, e.offsetY];
            }

            function handleTouch(e) {
                e.preventDefault();
                if (e.type === 'touchstart') {
                    startDrawing(e);
                } else if (e.type === 'touchmove') {
                    draw(e);
                }
            }

            // Effacer la signature
            document.getElementById('clearSignature').addEventListener('click', function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            });

            // Soumettre la signature
            document.getElementById('signatureForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const signatureData = canvas.toDataURL();
                document.getElementById('signature_data').value = signatureData;
                this.submit();
            });
        });
    </script>
</body>

</html>