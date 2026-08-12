<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
demarrerSession();
http_response_code(404);
$connecte = !empty($_SESSION['utilisateur_id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Introuvable — ImpôtsLocaux-SN</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="page-connexion">
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="text-center" style="max-width: 460px;">
            <div class="fs-1 mb-2" style="color: var(--couleur-accent);"><i class="bi bi-search"></i></div>
            <h1 class="h3 mb-2">Élément introuvable</h1>
            <p class="text-muted mb-4">La fiche demandée n'existe pas, ou a peut-être été supprimée.</p>
            <a href="<?= $connecte ? 'index.php' : 'connexion.php' ?>" class="btn btn-primary">
                <?= $connecte ? 'Retour au tableau de bord' : 'Se connecter' ?>
            </a>
        </div>
    </div>
</body>
</html>
<?php exit; ?>