<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
demarrerSession();
http_response_code(403);
$roleActuel = $_SESSION['utilisateur_role'] ?? null;
$titrePage = 'Accès refusé';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès refusé — ImpôtsLocaux-SN</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="page-connexion">
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="text-center" style="max-width: 460px;">
            <div class="fs-1 mb-2" style="color: var(--couleur-accent);"><i class="bi bi-shield-lock"></i></div>
            <h1 class="h3 mb-2">Accès refusé</h1>
            <p class="text-muted mb-4">
                <?php if ($roleActuel): ?>
                    Votre rôle actuel (<strong><?= e($roleActuel) ?></strong>) ne permet pas d'accéder à cette page.
                <?php else: ?>
                    Vous devez être connecté pour accéder à cette page.
                <?php endif; ?>
            </p>
            <a href="<?= $roleActuel ? 'index.php' : 'connexion.php' ?>" class="btn btn-primary">
                <?= $roleActuel ? 'Retour au tableau de bord' : 'Se connecter' ?>
            </a>
        </div>
    </div>
</body>
</html>
<?php exit; ?>